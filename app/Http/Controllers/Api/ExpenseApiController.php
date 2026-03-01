<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseAudit;
use App\Models\ExpenseAttachment;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserColumnPreference;
use App\Support\RbacPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;

    private const MODULE = 'expenses';
    private const PRODUCT_CATEGORIES = ['Staionary', 'water', 'It', 'telecom', 'nol/taxi', 'others'];

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
        $this->authorizeAction($request, 'read', ['expense_tracker.list', 'expense_tracker.view']);

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
            'product_category' => ['sometimes', 'nullable', 'string', Rule::in(self::PRODUCT_CATEGORIES)],
            'added_by' => ['sometimes', 'nullable', 'string', 'max:200'],
            'amount_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'vat_applicable' => ['sometimes', 'nullable', 'string', Rule::in(['all', 'yes', 'no'])],
            'product_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:190'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Expense::STATUSES)],
            'added_by_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? config('modules.expenses.default_sort.0', 'expense_date');
        $order = $validated['order'] ?? config('modules.expenses.default_sort.1', 'desc');

        $baseQuery = Expense::query();
        $this->applyFilters($baseQuery, $validated, $request->user());
        $total = $baseQuery->count();

        $dataQuery = Expense::query()
            ->select($this->buildExpenseSelectColumns($columns))
            ->with('user:id,name');
        $this->applyFilters($dataQuery, $validated, $request->user());
        $this->applySort($dataQuery, $sort, $order);
        $items = $dataQuery->skip(($page - 1) * $perPage)->take($perPage)->get()
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
        $this->authorizeAction($request, 'read', ['expense_tracker.list', 'expense_tracker.view']);

        $query = Expense::query()->selectRaw('COUNT(*) as total, COALESCE(SUM(full_amount), 0) as sum_amount, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved_count', [Expense::STATUS_PENDING, Expense::STATUS_APPROVED]);
        if (! $request->user()->hasRole('superadmin')) {
            $query->where('user_id', $request->user()->id);
        }
        $row = $query->first();

        return response()->json([
            'total_expenses' => (int) ($row->total ?? 0),
            'total_amount' => round((float) ($row->sum_amount ?? 0), 2),
            'pending_approval' => (int) ($row->pending_count ?? 0),
            'approved' => (int) ($row->approved_count ?? 0),
        ]);
    }

    public function filters(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['expense_tracker.list', 'expense_tracker.view']);

        $user = $request->user();
        $cacheKey = 'expense_filters_' . ($user->hasRole('superadmin') ? 'sa' : $user->id);
        $data = Cache::remember($cacheKey, 600, function () use ($request) {
            $categories = collect(self::PRODUCT_CATEGORIES)
                ->map(fn ($c) => ['value' => $c, 'label' => $this->formatProductCategoryLabel($c)])
                ->values()
                ->all();

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

            return [
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
            ];
        });

        return response()->json($data);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['expense_tracker.list', 'expense_tracker.view']);

        $config = config('modules.expenses.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            $allColumns[] = ['key' => $key, 'label' => $def['label'] ?? $key];
        }

        $cacheKey = 'expense_columns_pref_' . $request->user()->id;
        $visible = Cache::remember($cacheKey, 3600, function () use ($request) {
            $pref = UserColumnPreference::where('user_id', $request->user()->id)
                ->where('module', self::MODULE)
                ->first(['visible_columns']);
            return $pref?->visible_columns ?? config('modules.expenses.default_columns', []);
        });

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['expense_tracker.list', 'expense_tracker.view']);

        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );
        Cache::forget("col_pref_{$request->user()->id}_" . self::MODULE);
        Cache::forget('expense_columns_pref_' . $request->user()->id);

        return response()->json(['success' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'create', ['expense_tracker.create']);

        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'product_category' => ['required', 'string', Rule::in(self::PRODUCT_CATEGORIES)],
            'product_description' => ['required', 'string', 'max:65535'],
            'invoice_number' => ['nullable', 'string', 'max:190'],
            'comment' => ['required', 'string', 'max:65535'],
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

        $this->storeAttachments($request, $expense);
        $this->writeAuditLog($expense, 'created', null, $expense->fresh()->toArray());
        $this->invalidateExpenseFiltersCache($expense);

        return response()->json(['message' => 'Expense created.', 'data' => ['id' => $expense->id]], 201);
    }

    private function storeAttachments(Request $request, Expense $expense): void
    {
        $baseDir = 'expense-attachments/' . $expense->id;
        $invoiceFiles = $request->file('invoice');
        if (! is_array($invoiceFiles)) {
            $invoiceFiles = $invoiceFiles ? [$invoiceFiles] : [];
        }
        foreach ($invoiceFiles as $file) {
            if (! $file->isValid()) {
                continue;
            }
            $path = $file->store($baseDir, 'local');
            $expense->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => 'local',
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'type' => 'invoice',
            ]);
        }
        $supportingFiles = $request->file('supporting_documents');
        if (! is_array($supportingFiles)) {
            $supportingFiles = $supportingFiles ? [$supportingFiles] : [];
        }
        foreach ($supportingFiles as $file) {
            if (! $file->isValid()) {
                continue;
            }
            $path = $file->store($baseDir, 'local');
            $expense->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => 'local',
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'type' => 'supporting',
            ]);
        }
    }

    public function show(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['expense_tracker.view', 'expense_tracker.list']);
        if ($expense->user_id !== $request->user()->id && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }
        $expense->load(['user:id,name', 'attachments']);
        return response()->json(['data' => $this->formatShowData($expense)]);
    }

    public function downloadAttachment(Request $request, Expense $expense, ExpenseAttachment $attachment): StreamedResponse
    {
        if ($attachment->expense_id !== $expense->id) {
            abort(404);
        }
        $this->authorizeAction($request, 'read', ['expense_tracker.view', 'expense_tracker.list']);
        if ($expense->user_id !== $request->user()->id && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }
        $path = $attachment->path;
        $disk = $attachment->disk ?? 'local';
        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }
        return Storage::disk($disk)->response($path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
        ]);
    }

    public function destroyAttachment(Expense $expense, ExpenseAttachment $attachment): JsonResponse
    {
        $request = request();
        if ($attachment->expense_id !== $expense->id) {
            abort(404);
        }
        $this->authorizeAction($request, 'update', ['expense_tracker.edit', 'expense_tracker.update']);
        if ($expense->user_id !== $request->user()->id && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }
        $disk = $attachment->disk ?? 'local';
        if (Storage::disk($disk)->exists($attachment->path)) {
            Storage::disk($disk)->delete($attachment->path);
        }
        try {
            SystemAuditLog::record('expense.attachment_deleted', ['expense_id' => $expense->id, 'attachment_id' => $attachment->id, 'attachment_name' => $attachment->original_name], null, $request->user()->id, 'expense', $expense->id);
        } catch (\Throwable $e) {}
        $attachment->delete();
        return response()->json(['message' => 'Attachment removed.']);
    }

    public function addAttachments(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, 'update', ['expense_tracker.edit', 'expense_tracker.update']);
        if ($expense->user_id !== $request->user()->id && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }
        $this->storeAttachments($request, $expense);
        $expense->load(['user:id,name', 'attachments']);
        $attachmentCount = $expense->attachments->count();
        try {
            SystemAuditLog::record('expense.attachments_added', null, ['expense_id' => $expense->id, 'attachment_count' => $attachmentCount], $request->user()->id, 'expense', $expense->id);
        } catch (\Throwable $e) {}
        $data = $this->formatShowData($expense);
        return response()->json(['message' => 'Attachments added.', 'data' => $data]);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, 'update', ['expense_tracker.edit', 'expense_tracker.update']);
        if ($expense->user_id !== $request->user()->id && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }

        $validated = $request->validate([
            'expense_date' => ['sometimes', 'required', 'date'],
            'product_category' => ['sometimes', 'required', 'string', Rule::in(self::PRODUCT_CATEGORIES)],
            'product_description' => ['sometimes', 'required', 'string', 'max:65535'],
            'invoice_number' => ['nullable', 'string', 'max:190'],
            'comment' => ['sometimes', 'required', 'string', 'max:65535'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_without_vat' => ['sometimes', 'required', 'numeric', 'min:0'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Expense::STATUSES)],
        ]);

        $old = $expense->only(array_keys($validated));
        $userId = $expense->user_id;
        if ($request->user()->hasRole('superadmin') && array_key_exists('user_id', $validated)) {
            $userId = $validated['user_id'] ? (int) $validated['user_id'] : $expense->user_id;
        } elseif (! $request->user()->hasRole('superadmin')) {
            unset($validated['user_id']);
        }

        $vatPercent = array_key_exists('vat_percent', $validated) ? (float) $validated['vat_percent'] : (float) $expense->getRawOriginal('vat_amount');
        $amountWithoutVat = array_key_exists('amount_without_vat', $validated) ? (float) $validated['amount_without_vat'] : (float) $expense->amount_without_vat;
        $vatAmountCurrency = round($amountWithoutVat * ($vatPercent / 100), 2);
        $fullAmount = round($amountWithoutVat + $vatAmountCurrency, 2);

        $expense->fill([
            'expense_date' => $validated['expense_date'] ?? $expense->expense_date,
            'product_category' => isset($validated['product_category']) ? trim($validated['product_category']) : $expense->product_category,
            'product_description' => isset($validated['product_description']) ? trim($validated['product_description']) : $expense->product_description,
            'invoice_number' => array_key_exists('invoice_number', $validated) ? (trim($validated['invoice_number']) ?: null) : $expense->invoice_number,
            'comment' => isset($validated['comment']) ? trim($validated['comment']) : $expense->comment,
            'vat_amount' => $vatPercent,
            'amount_without_vat' => $amountWithoutVat,
            'full_amount' => $fullAmount,
            'user_id' => $userId,
            'status' => array_key_exists('status', $validated) ? ($validated['status'] ?: Expense::STATUS_PENDING) : $expense->status,
        ]);
        $expense->save();

        $newValues = $expense->fresh()->only(array_merge(array_keys($validated), ['vat_amount', 'amount_without_vat', 'full_amount']));
        $this->writeAuditLog($expense, 'updated', $old, $newValues);

        $this->invalidateExpenseFiltersCache($expense);
        $expense->load(['user:id,name', 'attachments']);
        $data = $this->formatShowData($expense);
        return response()->json(['message' => 'Updated.', 'data' => $data]);
    }

    private function invalidateExpenseFiltersCache(Expense $expense): void
    {
        Cache::forget('expense_filters_' . $expense->user_id);
        if (request()->user()?->hasRole('superadmin')) {
            Cache::forget('expense_filters_sa');
        }
    }

    private function formatShowData(Expense $expense): array
    {
        $vatRateRaw = $expense->getRawOriginal('vat_amount');
        $vatRate = $vatRateRaw !== null ? (float) $vatRateRaw : null;
        $vatCurrency = $vatRate !== null ? round((float) $expense->amount_without_vat * ($vatRate / 100), 2) : null;
        $baseUrl = url('/api');
        $attachments = $expense->attachments->map(function (ExpenseAttachment $att) use ($baseUrl) {
            return [
                'id' => $att->id,
                'original_name' => $att->original_name,
                'filename' => $att->original_name,
                'name' => $att->original_name,
                'mime_type' => $att->mime_type,
                'type' => $att->isImage() ? 'image' : (str_starts_with($att->mime_type ?? '', 'application/pdf') ? 'PDF Document' : 'Document'),
                'url' => $baseUrl . '/expenses/' . $att->expense_id . '/attachments/' . $att->id . '/download',
                'is_image' => $att->isImage(),
            ];
        })->values()->all();
        return [
            'id' => $expense->id,
            'user_id' => $expense->user_id,
            'expense_id' => 'EXP-' . date('Y', $expense->created_at?->getTimestamp() ?: time()) . '-' . str_pad((string) $expense->id, 3, '0', STR_PAD_LEFT),
            'expense_date' => $expense->expense_date?->format('d M Y'),
            'expense_date_raw' => $expense->expense_date?->format('Y-m-d'),
            'product_category' => $expense->product_category,
            'product_description' => $expense->product_description,
            'invoice_number' => $expense->invoice_number,
            'comment' => $expense->comment,
            'vat_percent' => $vatRate,
            'vat_amount_currency' => $vatCurrency,
            'amount_without_vat' => (float) $expense->amount_without_vat,
            'full_amount' => (float) $expense->full_amount,
            'added_by' => $expense->user?->name ?? '—',
            'created_at' => $expense->created_at?->format('d M Y'),
            'created_at_raw' => $expense->created_at?->format('Y-m-d'),
            'status' => $expense->status ?? Expense::STATUS_PENDING,
            'attachments' => $attachments,
        ];
    }

    private function writeAuditLog(Expense $expense, string $action, ?array $oldValues, ?array $newValues): void
    {
        ExpenseAudit::create([
            'expense_id' => $expense->id,
            'user_id' => request()->user()?->id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    public function auditLog(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['expense_tracker.view', 'expense_tracker.list']);
        if ($expense->user_id !== $request->user()->id && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }
        $audits = $expense->audits()
            ->with(['user:id,name', 'user.roles:id,name'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($a) {
                $user = $a->user;
                $roleName = $user && $user->relationLoaded('roles') ? $user->roles->first()?->name : null;
                return [
                    'id' => $a->id,
                    'action' => $a->action,
                    'old_values' => $a->old_values,
                    'new_values' => $a->new_values,
                    'user_name' => $user?->name ?? 'System',
                    'user_role' => $roleName,
                    'created_at' => $a->created_at?->toIso8601String(),
                ];
            });

        $audits = $this->resolveObjectBasedAuditValues($audits);

        return response()->json(['data' => $audits]);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeAction($request, 'delete', ['expense_tracker.delete']);
        if ($expense->user_id !== $request->user()->id && ! $request->user()->hasRole('superadmin')) {
            abort(403);
        }
        $userId = $expense->user_id;
        $old = $expense->toArray();
        $this->writeAuditLog($expense, 'deleted', $old, null);
        $expense->delete();
        Cache::forget('expense_filters_' . $userId);
        if ($request->user()?->hasRole('superadmin')) {
            Cache::forget('expense_filters_sa');
        }
        return response()->json(['message' => 'Expense deleted.']);
    }

    private function authorizeAction(Request $request, string $action, array $legacy = []): void
    {
        $user = $request->user();

        if (! $user || ! RbacPermission::can($user, ['expense_tracker', 'expenses'], $action, $legacy)) {
            abort(403, 'Unauthorized.');
        }
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
            'amount_min' => $validated['amount_min'] ?? null,
            'amount_max' => $validated['amount_max'] ?? null,
            'vat_applicable' => ($validated['vat_applicable'] ?? null) === 'all' ? null : ($validated['vat_applicable'] ?? null),
            'product_description' => $validated['product_description'] ?? null,
            'invoice' => $validated['invoice_number'] ?? null,
            'status' => $validated['status'] ?? null,
        ];
        if (! empty($validated['added_by_user_id'])) {
            $filters['user_id'] = (int) $validated['added_by_user_id'];
        } else {
            $filters['added_by'] = $validated['added_by'] ?? null;
        }
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

    /** Only columns that exist on the expenses table (vat_amount_currency and added_by are computed in formatRow). */
    private const EXPENSE_TABLE_COLUMNS = [
        'id', 'user_id', 'expense_date', 'product_category', 'product_description', 'invoice_number',
        'vat_amount', 'amount_without_vat', 'full_amount', 'created_at', 'status',
    ];

    private function buildExpenseSelectColumns(array $columns): array
    {
        return self::EXPENSE_TABLE_COLUMNS;
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
            'vat_amount_raw' => $vatRate,
            'amount_without_vat' => number_format((float) $row->amount_without_vat, 2),
            'amount_without_vat_raw' => (float) $row->amount_without_vat,
            'vat_amount_currency' => $vatCurrency !== null ? number_format($vatCurrency, 2) : '—',
            'full_amount' => number_format((float) $row->full_amount, 2),
            'added_by' => $row->user?->name ?? '—',
            'created_at' => $row->created_at?->format('d M Y'),
            'created_at_raw' => $row->created_at?->format('Y-m-d'),
            'status' => $row->status ?? Expense::STATUS_PENDING,
        ];
        $allowed = array_flip($columns);
        $allowed['id'] = $allowed['expense_id'] = $allowed['expense_date_raw'] = $allowed['vat_amount_raw'] = $allowed['amount_without_vat_raw'] = true;
        return array_intersect_key($out, $allowed);
    }

    private function formatProductCategoryLabel(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }
        if (strtolower($trimmed) === 'it') {
            return 'IT';
        }

        return collect(explode('/', $trimmed))
            ->map(function (string $part): string {
                $part = trim($part);
                if ($part === '') {
                    return $part;
                }
                if (strtolower($part) === 'it') {
                    return 'IT';
                }
                return ucfirst(strtolower($part));
            })
            ->implode('/');
    }
}
