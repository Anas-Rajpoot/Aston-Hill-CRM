<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ExpenseApiController extends Controller
{
    private const MODULE = 'expenses';

    private const ALLOWED_COLUMNS = [
        'id', 'expense_date', 'product_category', 'product_description', 'invoice_number',
        'vat_amount', 'amount_without_vat', 'vat_amount_currency', 'full_amount',
        'added_by', 'created_at', 'status',
    ];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('expense_tracker.list') && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(self::ALLOWED_COLUMNS)],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
            'expense_date_from' => ['sometimes', 'nullable', 'date'],
            'expense_date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:expense_date_from'],
            'created_from' => ['sometimes', 'nullable', 'date'],
            'created_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:created_from'],
            'product_category' => ['sometimes', 'nullable', 'string', 'max:190'],
            'added_by' => ['sometimes', 'nullable', 'string', 'max:200'],
            'amount_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'vat_applicable' => ['sometimes', 'nullable', 'string', Rule::in(['all', 'yes', 'no'])],
            'product_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:190'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Expense::STATUSES)],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? config('modules.expenses.default_sort.0', 'expense_date');
        $order = $validated['order'] ?? config('modules.expenses.default_sort.1', 'desc');

        $query = Expense::query()->with('user:id,name');
        $this->applyFilters($query, $validated, $request->user());
        $total = $query->count();

        $this->applySort($query, $sort, $order);
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get()
            ->map(fn ($row) => $this->formatRow($row, $columns));

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        if (! $request->user()->can('expense_tracker.list') && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }

        $query = Expense::query();
        if (! $request->user()->hasRole('superadmin')) {
            $query->where('user_id', $request->user()->id);
        }

        $totalExpenses = $query->count();
        $totalAmount = (float) $query->sum('full_amount');

        $pendingQuery = clone $query;
        $pendingCount = (int) $pendingQuery->where('status', Expense::STATUS_PENDING)->count();

        $approvedQuery = clone $query;
        $approvedCount = (int) $approvedQuery->where('status', Expense::STATUS_APPROVED)->count();

        return response()->json([
            'total_expenses' => $totalExpenses,
            'total_amount' => round($totalAmount, 2),
            'pending_approval' => $pendingCount,
            'approved' => $approvedCount,
        ]);
    }

    public function filters(Request $request): JsonResponse
    {
        if (! $request->user()->can('expense_tracker.list') && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }

        $query = Expense::query();
        if (! $request->user()->hasRole('superadmin')) {
            $query->where('user_id', $request->user()->id);
        }

        $categories = $query->clone()->distinct()->pluck('product_category')->filter()->sort()->values()
            ->map(fn ($c) => ['value' => $c, 'label' => $c])->all();

        $vatPercentOptions = [
            ['value' => 0, 'label' => '0% (Exempt)'],
            ['value' => 5, 'label' => '5%'],
            ['value' => 15, 'label' => '15%'],
        ];

        $usersForAddedBy = [];
        if ($request->user()->hasRole('superadmin')) {
            $usersForAddedBy = User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])
                ->values()
                ->all();
        }

        return response()->json([
            'categories' => $categories,
            'vat_options' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'no', 'label' => 'No'],
            ],
            'vat_percent_options' => $vatPercentOptions,
            'added_by_users' => $usersForAddedBy,
            'status_options' => [
                ['value' => 'pending', 'label' => 'Pending Approval'],
                ['value' => 'approved', 'label' => 'Approved'],
            ],
        ]);
    }

    public function columns(Request $request): JsonResponse
    {
        if (! $request->user()->can('expense_tracker.list') && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }

        $config = config('modules.expenses.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            $allColumns[] = ['key' => $key, 'label' => $def['label'] ?? $key];
        }

        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', self::MODULE)
            ->first();
        $visible = $pref?->visible_columns ?? config('modules.expenses.default_columns', []);

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        if (! $request->user()->can('expense_tracker.list') && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }

        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );
        Cache::forget("col_pref_{$request->user()->id}_" . self::MODULE);

        return response()->json(['success' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->can('expense_tracker.create') && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }

        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'product_category' => ['required', 'string', 'max:190'],
            'product_description' => ['required', 'string', 'max:65535'],
            'invoice_number' => ['nullable', 'string', 'max:190'],
            'comment' => ['nullable', 'string', 'max:65535'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_without_vat' => ['required', 'numeric', 'min:0'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $userId = $request->user()->id;
        if ($request->user()->hasRole('superadmin') && ! empty($validated['user_id'])) {
            $userId = (int) $validated['user_id'];
        }

        $vatPercent = isset($validated['vat_percent']) ? (float) $validated['vat_percent'] : 0;
        $amountWithoutVat = (float) $validated['amount_without_vat'];
        $vatAmountCurrency = round($amountWithoutVat * ($vatPercent / 100), 2);
        $fullAmount = round($amountWithoutVat + $vatAmountCurrency, 2);

        $expense = Expense::create([
            'user_id' => $userId,
            'expense_date' => $validated['expense_date'],
            'product_category' => trim($validated['product_category']),
            'product_description' => trim($validated['product_description']),
            'invoice_number' => isset($validated['invoice_number']) ? trim($validated['invoice_number']) : null,
            'comment' => isset($validated['comment']) ? trim($validated['comment']) : null,
            'vat_amount' => $vatPercent,
            'amount_without_vat' => $amountWithoutVat,
            'full_amount' => $fullAmount,
            'status' => Expense::STATUS_PENDING,
        ]);

        return response()->json(['message' => 'Expense created.', 'data' => ['id' => $expense->id]], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        if (! request()->user()->can('expense_tracker.view') && ! request()->user()->can('expense_tracker.list') && ! request()->user()->hasRole('superadmin')) {
            abort(403);
        }
        if ($expense->user_id !== request()->user()->id && ! request()->user()->hasRole('superadmin')) {
            abort(403);
        }
        $expense->load('user:id,name');
        $vatRateRaw = $expense->getRawOriginal('vat_amount');
        $vatRate = $vatRateRaw !== null ? (float) $vatRateRaw : null;
        $vatCurrency = $vatRate !== null ? round((float) $expense->amount_without_vat * ($vatRate / 100), 2) : null;
        $data = [
            'id' => $expense->id,
            'expense_id' => 'EXP-' . date('Y', $expense->created_at?->getTimestamp() ?: time()) . '-' . str_pad((string) $expense->id, 3, '0', STR_PAD_LEFT),
            'expense_date' => $expense->expense_date?->format('d M Y'),
            'expense_date_raw' => $expense->expense_date?->format('Y-m-d'),
            'product_category' => $expense->product_category,
            'product_description' => $expense->product_description,
            'invoice_number' => $expense->invoice_number,
            'comment' => $expense->comment,
            'vat_percent' => $vatRate !== null ? (float) $vatRate : null,
            'vat_amount_currency' => $vatCurrency,
            'amount_without_vat' => (float) $expense->amount_without_vat,
            'full_amount' => (float) $expense->full_amount,
            'added_by' => $expense->user?->name ?? '—',
            'created_at' => $expense->created_at?->format('d M Y'),
            'created_at_raw' => $expense->created_at?->format('Y-m-d'),
            'status' => $expense->status ?? Expense::STATUS_PENDING,
        ];
        return response()->json(['data' => $data]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        if (! request()->user()->can('expense_tracker.delete') && ! request()->user()->hasRole('superadmin')) {
            abort(403);
        }
        if ($expense->user_id !== request()->user()->id && ! request()->user()->hasRole('superadmin')) {
            abort(403);
        }
        $expense->delete();
        return response()->json(['message' => 'Expense deleted.']);
    }

    private function applyFilters($query, array $validated, $user): void
    {
        if (! $user->hasRole('superadmin')) {
            $query->where('user_id', $user->id);
        }

        $filters = [
            'from' => $validated['expense_date_from'] ?? null,
            'to' => $validated['expense_date_to'] ?? null,
            'created_from' => $validated['created_from'] ?? null,
            'created_to' => $validated['created_to'] ?? null,
            'category' => $validated['product_category'] ?? null,
            'added_by' => $validated['added_by'] ?? null,
            'amount_min' => $validated['amount_min'] ?? null,
            'amount_max' => $validated['amount_max'] ?? null,
            'vat_applicable' => ($validated['vat_applicable'] ?? null) === 'all' ? null : ($validated['vat_applicable'] ?? null),
            'product_description' => $validated['product_description'] ?? null,
            'invoice' => $validated['invoice_number'] ?? null,
            'status' => $validated['status'] ?? null,
        ];
        $query->filter($filters);
    }

    private function applySort($query, string $sort, string $order): void
    {
        $dir = strtolower($order) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'added_by') {
            $query->leftJoin('users', 'expenses.user_id', '=', 'users.id')
                ->orderBy('users.name', $dir)
                ->select('expenses.*');
            return;
        }
        $query->orderBy('expenses.' . $sort, $dir);
    }

    private function resolveColumns($user, ?array $requestColumns): array
    {
        if (! empty($requestColumns)) {
            return array_values(array_unique(array_intersect($requestColumns, self::ALLOWED_COLUMNS)));
        }
        $cacheKey = "col_pref_{$user->id}_" . self::MODULE;
        $preference = Cache::remember($cacheKey, 3600, function () use ($user) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', self::MODULE)
                ->first();
        });
        $cols = $preference?->visible_columns ?? config('modules.expenses.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        return array_values(array_intersect($cols, self::ALLOWED_COLUMNS));
    }

    private function formatRow(Expense $row, array $columns): array
    {
        $vatRateRaw = $row->getRawOriginal('vat_amount');
        $vatRate = $vatRateRaw !== null ? (float) $vatRateRaw : null;
        $vatCurrency = $vatRate !== null ? round((float) $row->amount_without_vat * ($vatRate / 100), 2) : null;

        $out = [
            'id' => $row->id,
            'expense_date' => $row->expense_date?->format('d M Y'),
            'expense_date_raw' => $row->expense_date?->format('Y-m-d'),
            'product_category' => $row->product_category,
            'product_description' => $row->product_description,
            'invoice_number' => $row->invoice_number,
            'vat_amount' => $vatRate !== null ? number_format($vatRate, 2) : '—',
            'amount_without_vat' => number_format((float) $row->amount_without_vat, 2),
            'vat_amount_currency' => $vatCurrency !== null ? number_format($vatCurrency, 2) : '—',
            'full_amount' => number_format((float) $row->full_amount, 2),
            'added_by' => $row->user?->name ?? '—',
            'created_at' => $row->created_at?->format('d M Y'),
            'created_at_raw' => $row->created_at?->format('Y-m-d'),
            'status' => $row->status ?? Expense::STATUS_PENDING,
        ];
        $allowed = array_flip($columns);
        return array_intersect_key($out, $allowed + ['id' => true]);
    }
}
