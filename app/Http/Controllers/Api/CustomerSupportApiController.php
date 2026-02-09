<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerSupportController;
use App\Http\Controllers\FieldSubmissionController;
use App\Models\CustomerSupportSubmission;
use App\Models\CustomerSupportSubmissionAudit;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerSupportApiController extends Controller
{
    private const MODULE = 'customer_support_submissions';

    private const ALLOWED_COLUMNS = [
        'id', 'submitted_at', 'created_at', 'created_by',
        'issue_category', 'company_name', 'account_number', 'contact_number',
        'issue_description', 'attachments',
        'manager_id', 'team_leader_id', 'sales_agent_id',
        'status',
    ];

    private const BASE_COLUMNS = ['id', 'status'];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSupportSubmission::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']))],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'issue_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(CustomerSupportSubmission::STATUSES)],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'submitted_at';
        $order = $validated['order'] ?? 'desc';

        $baseQuery = CustomerSupportSubmission::query();
        $this->applyFilters($baseQuery, $validated);
        $total = $baseQuery->count();

        $dataQuery = CustomerSupportSubmission::query();
        $this->applyFilters($dataQuery, $validated);
        $this->applySort($dataQuery, $sort, $order);

        $selectColumns = $this->buildSelectColumns($columns);
        $dataQuery->select($selectColumns);

        $eagerLoad = [];
        if (in_array('creator', $columns, true)) {
            $eagerLoad['creator'] = fn ($q) => $q->select('id', 'name', 'email');
        }
        if (in_array('manager', $columns, true) || in_array('team_leader', $columns, true) || in_array('sales_agent', $columns, true)) {
            $eagerLoad['manager'] = fn ($q) => $q->select('id', 'name');
            $eagerLoad['teamLeader'] = fn ($q) => $q->select('id', 'name');
            $eagerLoad['salesAgent'] = fn ($q) => $q->select('id', 'name');
        }
        if (! empty($eagerLoad)) {
            $dataQuery->with($eagerLoad);
        }

        $offset = ($page - 1) * $perPage;
        $items = $dataQuery->skip($offset)->take($perPage)->get()->map(function ($row) use ($columns) {
            return $this->formatRow($row, $columns);
        });

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

    private function resolveColumns($user, ?array $requestColumns): array
    {
        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']);
        if (! empty($requestColumns)) {
            $allowed = array_intersect($requestColumns, $allAllowed);
            return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
        }

        $cacheKey = "col_pref_{$user->id}_" . self::MODULE;
        $preference = Cache::remember($cacheKey, 3600, function () use ($user) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', self::MODULE)
                ->first();
        });

        $cols = $preference?->visible_columns ?? config('modules.customer_support_submissions.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        $allowed = array_intersect($cols, $allAllowed);
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function applyFilters($query, array $validated): void
    {
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from'] . ' 00:00:00');
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }
        if (! empty($validated['submitted_from'])) {
            $query->where('submitted_at', '>=', $validated['submitted_from'] . ' 00:00:00');
        }
        if (! empty($validated['submitted_to'])) {
            $query->where('submitted_at', '<=', $validated['submitted_to'] . ' 23:59:59');
        }
        if (! empty($validated['company_name'])) {
            $term = '%' . addcslashes($validated['company_name'], '%_\\') . '%';
            $query->where('company_name', 'like', $term);
        }
        if (! empty($validated['account_number'])) {
            $term = '%' . addcslashes($validated['account_number'], '%_\\') . '%';
            $query->where('account_number', 'like', $term);
        }
        if (! empty($validated['contact_number'])) {
            $term = '%' . addcslashes($validated['contact_number'], '%_\\') . '%';
            $query->where('contact_number', 'like', $term);
        }
        if (! empty($validated['issue_category'])) {
            $query->where('issue_category', $validated['issue_category']);
        }
        if (! empty($validated['manager_id'])) {
            $query->where('manager_id', $validated['manager_id']);
        }
        if (! empty($validated['team_leader_id'])) {
            $query->where('team_leader_id', $validated['team_leader_id']);
        }
        if (! empty($validated['sales_agent_id'])) {
            $query->where('sales_agent_id', $validated['sales_agent_id']);
        }
        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'like', $term)
                    ->orWhere('account_number', 'like', $term)
                    ->orWhere('contact_number', 'like', $term)
                    ->orWhere('issue_description', 'like', $term);
            });
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        $direction = strtolower($order) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'creator') {
            $query->leftJoin('users as creator_users', 'customer_support_submissions.created_by', '=', 'creator_users.id')
                ->orderBy('creator_users.name', $direction);
            return;
        }
        if (in_array($sort, ['manager', 'team_leader', 'sales_agent'], true)) {
            $col = $sort . '_id';
            $alias = $sort . '_users';
            $query->leftJoin("users as {$alias}", "customer_support_submissions.{$col}", '=', "{$alias}.id")
                ->orderBy("{$alias}.name", $direction);
            return;
        }
        $query->orderBy('customer_support_submissions.' . $sort, $direction);
    }

    private function buildSelectColumns(array $columns): array
    {
        $base = ['customer_support_submissions.id', 'customer_support_submissions.status'];
        $map = [
            'submitted_at' => 'customer_support_submissions.submitted_at',
            'created_at' => 'customer_support_submissions.created_at',
            'created_by' => 'customer_support_submissions.created_by',
            'creator' => 'customer_support_submissions.created_by',
            'issue_category' => 'customer_support_submissions.issue_category',
            'company_name' => 'customer_support_submissions.company_name',
            'account_number' => 'customer_support_submissions.account_number',
            'contact_number' => 'customer_support_submissions.contact_number',
            'issue_description' => 'customer_support_submissions.issue_description',
            'attachments' => 'customer_support_submissions.attachments',
            'manager_id' => 'customer_support_submissions.manager_id',
            'manager' => 'customer_support_submissions.manager_id',
            'team_leader_id' => 'customer_support_submissions.team_leader_id',
            'team_leader' => 'customer_support_submissions.team_leader_id',
            'sales_agent_id' => 'customer_support_submissions.sales_agent_id',
            'sales_agent' => 'customer_support_submissions.sales_agent_id',
        ];
        foreach ($columns as $col) {
            if ($col === 'id' || $col === 'status') {
                continue;
            }
            if (isset($map[$col])) {
                $base[] = $map[$col];
            }
        }
        return array_unique($base);
    }

    private function formatRow(CustomerSupportSubmission $row, array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            if ($col === 'manager') {
                $out['manager_id'] = $row->manager_id;
                $out['manager'] = $row->manager?->name ?? null;
                continue;
            }
            if ($col === 'team_leader') {
                $out['team_leader_id'] = $row->team_leader_id;
                $out['team_leader'] = $row->teamLeader?->name ?? null;
                continue;
            }
            if ($col === 'sales_agent') {
                $out['sales_agent_id'] = $row->sales_agent_id;
                $out['sales_agent'] = $row->salesAgent?->name ?? null;
                continue;
            }
            if ($col === 'creator') {
                $out['creator'] = $row->creator?->name ?? null;
                continue;
            }
            if ($col === 'attachments') {
                $att = $row->attachments;
                $out['attachments'] = is_array($att) ? count($att) : 0;
                continue;
            }
            if (in_array($col, ['submitted_at', 'created_at'], true)) {
                $out[$col] = $row->$col ? $row->$col->format('d/M/Y H:i') : null;
                continue;
            }
            $out[$col] = $row->$col ?? null;
        }
        return $out;
    }

    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', CustomerSupportSubmission::class);

        $categories = CustomerSupportController::issueCategories();
        $issueCategories = array_map(fn ($c) => ['value' => $c, 'label' => $c], $categories);

        $teamOptions = app(FieldSubmissionController::class)->teamOptions(request());
        $teamData = $teamOptions->getData(true);
        $managers = $teamData['managers'] ?? [];
        $teamLeaders = $teamData['team_leaders'] ?? [];
        $salesAgents = $teamData['sales_agents'] ?? [];

        return response()->json([
            'issue_categories' => array_values($issueCategories),
            'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)], CustomerSupportSubmission::STATUSES),
            'managers' => $managers,
            'team_leaders' => $teamLeaders,
            'sales_agents' => $salesAgents,
        ]);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSupportSubmission::class);

        $config = config('modules.customer_support_submissions.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            $allColumns[] = [
                'key' => $key,
                'label' => $def['label'] ?? $key,
            ];
        }

        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', self::MODULE)
            ->first();

        $visible = $pref?->visible_columns ?? config('modules.customer_support_submissions.default_columns', []);

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSupportSubmission::class);

        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']);
        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in($allAllowed)],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );

        Cache::forget("col_pref_{$request->user()->id}_" . self::MODULE);

        return response()->json(['success' => true]);
    }

    public function patch(Request $request, CustomerSupportSubmission $customerSupportSubmission): JsonResponse
    {
        $this->authorize('update', $customerSupportSubmission);

        $categories = CustomerSupportController::issueCategories();
        $rules = [
            'issue_category' => ['sometimes', 'string', Rule::in($categories)],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_number' => ['sometimes', 'string', 'max:50'],
            'issue_description' => ['sometimes', 'string', 'max:5000'],
            'manager_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(CustomerSupportSubmission::STATUSES)],
        ];

        $data = $request->validate($rules);
        if (! empty($data)) {
            $oldValues = [];
            foreach (array_keys($data) as $key) {
                $val = $customerSupportSubmission->getAttribute($key);
                $oldValues[$key] = $val === null || $val === '' ? null : (is_object($val) ? json_encode($val) : (string) $val);
            }
            $customerSupportSubmission->update($data);
            $userId = $request->user()?->id;
            foreach ($data as $key => $newVal) {
                $newStr = $newVal === null || $newVal === '' ? null : (is_object($newVal) ? json_encode($newVal) : (string) $newVal);
                if (($oldValues[$key] ?? null) !== $newStr) {
                    $this->logChange($customerSupportSubmission->id, $key, $oldValues[$key] ?? null, $newStr, $userId);
                }
            }
        }

        $customerSupportSubmission->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'creator:id,name']);
        $columns = array_merge(
            ['id', 'submitted_at', 'created_at', 'issue_category', 'company_name', 'account_number', 'contact_number', 'issue_description', 'attachments', 'manager', 'team_leader', 'sales_agent', 'status', 'creator'],
            array_keys($data)
        );
        $columns = array_unique($columns);
        $row = $this->formatRow($customerSupportSubmission, $columns);

        return response()->json([
            'id' => $customerSupportSubmission->id,
            'message' => 'Updated.',
            'row' => $row,
        ]);
    }
}
