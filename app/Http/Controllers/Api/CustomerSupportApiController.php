<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerSupportController;
use App\Http\Controllers\FieldSubmissionController;
use App\Jobs\BulkAssignCsrJob;
use App\Models\CustomerSupportSubmission;
use App\Models\CustomerSupportSubmissionAudit;
use App\Models\SlaRule;
use App\Models\SystemAuditLog;
use App\Models\Client;
use App\Models\User;
use App\Models\UserColumnPreference;
use App\Policies\CustomerSupportSubmissionPolicy;
use App\Rules\AllowedDocumentFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class CustomerSupportApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;
    private const MODULE = 'customer_support_submissions';

    /** @var array<int, string>|null */
    private ?array $tableColumns = null;

    private const ALLOWED_COLUMNS = [
        'id', 'submitted_at', 'created_at', 'created_by',
        'ticket_number', 'issue_category', 'company_name', 'account_number', 'contact_number',
        'alternate_contact_number',
        'issue_description', 'attachments',
        'manager_id', 'team_leader_id', 'sales_agent_id',
        'csr_id', 'csr_name', 'status', 'workflow_status',
        'completion_date', 'updated_at',
        'trouble_ticket', 'activity', 'pending',
        'resolution_remarks', 'internal_remarks', 'sla_timer',
    ];

    private const BASE_COLUMNS = ['id', 'status'];
    private const STATUSS_EXCLUDED_FROM_LISTING = ['draft', 'submitted', 'approved'];

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
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'csr', 'creator']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'csr', 'creator']))],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'issue_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(array_merge(CustomerSupportSubmission::STATUSES, ['unassigned']))],
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

        // Single data query with sort, select, eager load, then paginate.
        $dataQuery = CustomerSupportSubmission::query()->visibleTo($user);
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
        if (in_array('csr', $columns, true) || in_array('csr_id', $columns, true)) {
            $eagerLoad['csrUser'] = fn ($q) => $q->select('id', 'name');
        }
        if (! empty($eagerLoad)) {
            $dataQuery->with($eagerLoad);
        }

        $offset = ($page - 1) * $perPage;
        $items = $dataQuery->skip($offset)->take($perPage)->get()->map(function ($row) use ($columns) {
            return $this->formatRow($row, $columns);
        });

        // Cache the count for 30s to avoid expensive COUNT(*) on every paginate/filter.
        $countCacheKey = 'cs_count_' . $user->id . '_' . md5(json_encode($validated));
        $total = Cache::remember($countCacheKey, 30, function () use ($user, $validated) {
            $cq = CustomerSupportSubmission::query()->visibleTo($user);
            $this->applyFilters($cq, $validated);
            return $cq->count();
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

    public function show(CustomerSupportSubmission $customerSupportSubmission): JsonResponse
    {
        $this->authorize('view', $customerSupportSubmission);

        $customerSupportSubmission->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'csrUser:id,name', 'creator:id,name', 'creator.roles:id,name']);
        $row = $customerSupportSubmission->toArray();
        $row['manager_id'] = $customerSupportSubmission->manager_id;
        $row['team_leader_id'] = $customerSupportSubmission->team_leader_id;
        $row['sales_agent_id'] = $customerSupportSubmission->sales_agent_id;
        $row['manager_name'] = $customerSupportSubmission->manager?->name;
        $row['team_leader_name'] = $customerSupportSubmission->teamLeader?->name;
        $row['sales_agent_name'] = $customerSupportSubmission->salesAgent?->name;
        $row['alternate_contact_number'] = $customerSupportSubmission->alternate_contact_number;
        $row['csr_user_name'] = $customerSupportSubmission->csrUser?->name;
        $row['creator_name'] = $customerSupportSubmission->creator?->name;
        $firstRole = $customerSupportSubmission->creator?->roles->first();
        $row['creator_role'] = $firstRole
            ? ucfirst(str_replace('_', ' ', $firstRole->name))
            : 'Customer Support Representative';
        $row['submitted_at'] = $customerSupportSubmission->submitted_at?->toIso8601String();
        $row['created_at'] = $customerSupportSubmission->created_at?->toIso8601String();
        $row['completion_date'] = $customerSupportSubmission->completion_date?->format('Y-m-d');

        $accountCsrNames = [];
        if ($customerSupportSubmission->account_number) {
            $client = Client::where('account_number', $customerSupportSubmission->account_number)->first();
            if ($client) {
                $accountCsrNames = DB::table('client_csrs')
                    ->join('users', 'client_csrs.user_id', '=', 'users.id')
                    ->where('client_csrs.client_id', $client->id)
                    ->orderBy('client_csrs.sort_order')
                    ->pluck('users.name')
                    ->all();
            }
        }
        $row['account_csr_names'] = $accountCsrNames;

        return response()->json($row);
    }

    public function destroy(Request $request, CustomerSupportSubmission $customerSupportSubmission): JsonResponse
    {
        $this->authorize('delete', $customerSupportSubmission);

        $submissionId = (int) $customerSupportSubmission->id;
        $customerSupportSubmission->delete();

        Storage::disk('public')->deleteDirectory("customer-support/{$submissionId}");

        SystemAuditLog::record('customer_support_submission.deleted', [
            'customer_support_submission_id' => $submissionId,
        ], null, $request->user()->id, 'customer_support_submission', $submissionId);

        return response()->json(['message' => 'Customer support request deleted.']);
    }

    private function resolveColumns($user, ?array $requestColumns): array
    {
        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'csr', 'creator']);
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
            if (strtolower((string) $validated['status']) === 'unassigned') {
                $query->whereNull('csr_id');
            } else {
                $query->where('status', $validated['status']);
            }
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
        $existing = $this->getExistingTableColumns();

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
        if ($sort === 'csr') {
            $query->leftJoin('users as csr_users', 'customer_support_submissions.csr_id', '=', 'csr_users.id')
                ->orderBy('csr_users.name', $direction);
            return;
        }
        if ($sort === 'sla_timer') {
            if (in_array('submitted_at', $existing, true)) {
                $query->orderByRaw("COALESCE(customer_support_submissions.submitted_at, customer_support_submissions.created_at) {$direction}");
            } else {
                $query->orderBy('customer_support_submissions.created_at', $direction);
            }
            return;
        }
        if (in_array($sort, $existing, true)) {
            $query->orderBy('customer_support_submissions.' . $sort, $direction);
            return;
        }

        $fallback = in_array('submitted_at', $existing, true) ? 'submitted_at' : 'created_at';
        $query->orderBy('customer_support_submissions.' . $fallback, $direction);
    }

    private function buildSelectColumns(array $columns): array
    {
        $base = ['id', 'status'];
        $map = [
            'submitted_at' => 'submitted_at',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'created_by' => 'created_by',
            'creator' => 'created_by',
            'issue_category' => 'issue_category',
            'company_name' => 'company_name',
            'account_number' => 'account_number',
            'contact_number' => 'contact_number',
            'alternate_contact_number' => 'alternate_contact_number',
            'issue_description' => 'issue_description',
            'attachments' => 'attachments',
            'manager_id' => 'manager_id',
            'manager' => 'manager_id',
            'team_leader_id' => 'team_leader_id',
            'team_leader' => 'team_leader_id',
            'sales_agent_id' => 'sales_agent_id',
            'sales_agent' => 'sales_agent_id',
            'csr_id' => 'csr_id',
            'csr' => 'csr_id',
            'csr_name' => 'csr_name',
            'ticket_number' => 'ticket_number',
            'workflow_status' => 'workflow_status',
            'completion_date' => 'completion_date',
            'trouble_ticket' => 'trouble_ticket',
            'activity' => 'activity',
            'pending' => 'pending',
            'resolution_remarks' => 'resolution_remarks',
            'internal_remarks' => 'internal_remarks',
        ];
        foreach ($columns as $col) {
            if ($col === 'id' || $col === 'status') {
                continue;
            }
            if (isset($map[$col])) {
                $base[] = $map[$col];
            }
        }
        if (in_array('sla_timer', $columns, true)) {
            $base[] = 'submitted_at';
            $base[] = 'created_at';
            $base[] = 'csr_id';
        }

        $base = array_unique($base);
        $existing = $this->getExistingTableColumns();
        $base = array_values(array_filter($base, fn ($c) => in_array($c, $existing, true)));

        return array_map(fn ($c) => 'customer_support_submissions.' . $c, $base);
    }

    /**
     * @return array<int, string>
     */
    private function getExistingTableColumns(): array
    {
        if ($this->tableColumns !== null) {
            return $this->tableColumns;
        }

        try {
            $this->tableColumns = Schema::getColumnListing('customer_support_submissions');
        } catch (\Throwable $e) {
            $this->tableColumns = self::ALLOWED_COLUMNS;
        }

        return $this->tableColumns;
    }

    private function formatRow(CustomerSupportSubmission $row, array $columns): array
    {
        $out = [];
        $status = $row->status ?? null;
        if (empty($row->csr_id)) {
            $statusText = strtolower((string) ($status ?? ''));
            if ($statusText === '' || in_array($statusText, self::STATUSS_EXCLUDED_FROM_LISTING, true)) {
                $status = 'unassigned';
            }
        }

        foreach ($columns as $col) {
            if ($col === 'manager') {
                $out['manager_id'] = $row->manager_id;
                $out['manager'] = $row->relationLoaded('manager') ? ($row->manager?->name ?? null) : null;
                continue;
            }
            if ($col === 'team_leader') {
                $out['team_leader_id'] = $row->team_leader_id;
                $out['team_leader'] = $row->relationLoaded('teamLeader') ? ($row->teamLeader?->name ?? null) : null;
                continue;
            }
            if ($col === 'sales_agent') {
                $out['sales_agent_id'] = $row->sales_agent_id;
                $out['sales_agent'] = $row->relationLoaded('salesAgent') ? ($row->salesAgent?->name ?? null) : null;
                continue;
            }
            if ($col === 'csr' || $col === 'csr_id') {
                $out['csr_id'] = $row->csr_id;
                $out['csr'] = $row->relationLoaded('csrUser') ? ($row->csrUser?->name ?? null) : ($row->csr_name ?? null);
                continue;
            }
            if ($col === 'creator') {
                $out['creator'] = $row->relationLoaded('creator') ? ($row->creator?->name ?? null) : null;
                continue;
            }
            if ($col === 'sla_timer') {
                $out['sla_timer'] = $this->computeSlaTimer($row);
                continue;
            }
            if ($col === 'attachments') {
                $att = $row->attachments;
                $out['attachments'] = is_array($att) ? count($att) : 0;
                continue;
            }
            if (in_array($col, ['submitted_at', 'created_at', 'updated_at'], true)) {
                $out[$col] = $row->$col ? $row->$col->format('d-M-Y H:i') : null;
                continue;
            }
            if ($col === 'completion_date') {
                $out[$col] = $row->completion_date ? $row->completion_date->format('d-M-Y') : null;
                continue;
            }
            if ($col === 'status') {
                $out['status'] = $status;
                continue;
            }

            $out[$col] = $row->$col ?? null;
        }
        return $out;
    }

    /** @return array<int, string> */
    private function listingStatuses(): array
    {
        $filtered = array_values(array_filter(
            CustomerSupportSubmission::STATUSES,
            fn ($s) => ! in_array(strtolower((string) $s), self::STATUSS_EXCLUDED_FROM_LISTING, true),
        ));

        return array_values(array_unique(array_merge(['unassigned'], $filtered)));
    }

    private function computeSlaTimer(CustomerSupportSubmission $row): ?string
    {
        $startAt = $row->submitted_at ?? $row->created_at;
        if (! $startAt) {
            return null;
        }
        if (! empty($row->csr_id)) {
            return 'Assigned';
        }

        $rule = SlaRule::cached()->firstWhere('module_key', 'customer_support_requests');
        $slaMinutes = ($rule && $rule->is_active)
            ? max(1, (int) $rule->sla_duration_minutes)
            : 1440;
        $warningMinutes = ($rule && $rule->is_active)
            ? max(0, (int) $rule->warning_threshold_minutes)
            : 120;

        $elapsed = $startAt->diffInMinutes(now());
        if ($elapsed <= $slaMinutes) {
            $remaining = $slaMinutes - $elapsed;
            if ($warningMinutes > 0 && $remaining <= $warningMinutes) {
                return 'Due in ' . $this->formatDuration($remaining);
            }
            return $this->formatDuration($elapsed) . ' passed of ' . $this->formatDuration($slaMinutes);
        }

        $overdue = $elapsed - $slaMinutes;
        return 'Breached by ' . $this->formatDuration($overdue);
    }

    private function formatDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) {
            return "{$h}h {$m}m";
        }
        if ($h > 0) {
            return "{$h}h";
        }
        return "{$m}m";
    }

    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', CustomerSupportSubmission::class);

        $userId = request()->user()->id;
        $data = \App\Services\SubmissionCacheService::rememberMeta('customer-support', 'filters', $userId, function () {
            $categories = CustomerSupportController::issueCategories();
            $issueCategories = array_map(fn ($c) => ['value' => $c, 'label' => $c], $categories);

            $teamOptions = app(FieldSubmissionController::class)->teamOptions(request());
            $teamData = $teamOptions->getData(true);
            $managers = $teamData['managers'] ?? [];
            $teamLeaders = $teamData['team_leaders'] ?? [];
            $salesAgents = $teamData['sales_agents'] ?? [];

            return [
                'issue_categories' => array_values($issueCategories),
                'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => $s === 'unassigned' ? 'UnAssigned' : ucfirst($s)], $this->listingStatuses()),
                'managers' => $managers,
                'team_leaders' => $teamLeaders,
                'sales_agents' => $salesAgents,
            ];
        });

        return response()->json($data);
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

        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'csr', 'creator']);
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
        $isAssignmentOnly = $request->exists('csr_id')
            && count(array_diff(array_keys($request->all()), ['csr_id', 'csr_name'])) === 0;

        if ($request->exists('csr_id')) {
            $this->authorize('assign', $customerSupportSubmission);
        }
        if (! $isAssignmentOnly) {
            $this->authorize('update', $customerSupportSubmission);
        }

        $categories = CustomerSupportController::issueCategories();
        $rules = [
            'issue_category' => ['sometimes', 'required', 'string', Rule::in($categories)],
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:50'],
            'alternate_contact_number' => ['sometimes', 'nullable', 'regex:/^971\d{9}$/'],
            'issue_description' => ['sometimes', 'required', 'string', 'max:5000'],
            'manager_id' => ['sometimes', 'required', 'integer', 'min:1', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(CustomerSupportSubmission::STATUSES)],
            'ticket_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'csr_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'workflow_status' => ['sometimes', 'nullable', 'string', Rule::in(CustomerSupportSubmission::WORKFLOW_STATUSES)],
            'completion_date' => ['sometimes', 'nullable', 'date'],
            'trouble_ticket' => ['sometimes', 'nullable', 'string', 'max:255'],
            'activity' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pending' => ['sometimes', 'nullable', 'string', 'max:255'],
            'resolution_remarks' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'internal_remarks' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
        $messages = [
            'issue_category.required' => 'Please select an issue category.',
            'company_name.required' => 'Company name is required.',
            'contact_number.required' => 'Contact number is required.',
            'issue_description.required' => 'Issue description is required.',
            'manager_id.required' => 'Please select a manager.',
            'manager_id.min' => 'Please select a manager.',
        ];

        $data = $request->validate($rules, $messages);
        if (array_key_exists('csr_id', $data) && ! empty($data['csr_id'])) {
            $assignee = User::find((int) $data['csr_id']);
            if (! $assignee || ! CustomerSupportSubmissionPolicy::isValidAssignee($assignee)) {
                return response()->json([
                    'message' => 'Selected assignee must be a CSR or support manager.',
                ], 422);
            }
        }
        if (! empty($data)) {
            if (isset($data['status']) && $data['status'] === 'submitted' && ! $customerSupportSubmission->submitted_at) {
                $data['submitted_at'] = now();
            }
            $customerSupportSubmission->update($data);
            // Update history is tracked by CustomerSupportSubmissionObserver -> customer_support_submission_audits
        }

        $customerSupportSubmission->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'creator:id,name', 'csrUser:id,name']);
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

    /**
     * GET edit form options: issue categories, workflow statuses, pending options.
     */
    public function editOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSupportSubmission::class);

        $excluded = ['draft', 'submitted', 'approved'];
        $editableStatuses = array_values(array_filter(
            CustomerSupportSubmission::STATUSES,
            fn ($s) => ! in_array(strtolower($s), $excluded),
        ));

        return response()->json([
            'issue_categories' => CustomerSupportController::issueCategories(),
            'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)], $editableStatuses),
            'workflow_statuses' => array_map(fn ($v) => ['value' => $v, 'label' => ucfirst(str_replace('_', ' ', $v))], CustomerSupportSubmission::WORKFLOW_STATUSES),
            'pending_options' => array_map(fn ($v) => ['value' => $v, 'label' => $v], CustomerSupportSubmission::PENDING_OPTIONS),
        ]);
    }

    /**
     * POST resubmit a customer support submission (allowed for all non-approved statuses).
     */
    public function resubmit(Request $request, CustomerSupportSubmission $customerSupportSubmission): JsonResponse
    {
        $this->authorize('update', $customerSupportSubmission);

        if ($customerSupportSubmission->status === 'approved') {
            return response()->json(['message' => 'Approved requests cannot be resubmitted.'], 422);
        }

        $categories = CustomerSupportController::issueCategories();
        $data = $request->validate([
            'issue_category' => ['required', 'string', Rule::in($categories)],
            'company_name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:50'],
            'alternate_contact_number' => ['nullable', 'regex:/^971\d{9}$/'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'manager_id' => ['required', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'ticket_number' => ['nullable', 'string', 'max:100'],
            'csr_name' => ['nullable', 'string', 'max:255'],
            'workflow_status' => ['nullable', 'string'],
            'completion_date' => ['nullable', 'date'],
            'trouble_ticket' => ['nullable', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
            'pending' => ['nullable', 'string', 'max:255'],
            'resolution_remarks' => ['nullable', 'string', 'max:5000'],
            'internal_remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $customerSupportSubmission->update(array_merge($data, [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]));

        return response()->json([
            'message' => 'Customer support request resubmitted successfully.',
            'id' => $customerSupportSubmission->id,
        ]);
    }

    /**
     * GET change history for a customer support submission.
     */
    public function audits(Request $request, CustomerSupportSubmission $customerSupportSubmission): JsonResponse
    {
        $this->authorize('view', $customerSupportSubmission);

        $rows = CustomerSupportSubmissionAudit::query()
            ->where('customer_support_submission_id', $customerSupportSubmission->id)
            ->with('changedByUser:id,name')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $data = $rows->map(function (CustomerSupportSubmissionAudit $audit) {
            return [
                'id' => $audit->id,
                'field_name' => $audit->field_name,
                'old_value' => $audit->old_value,
                'new_value' => $audit->new_value,
                'changed_at' => $audit->changed_at?->toIso8601String(),
                'changed_by' => $audit->changed_by,
                'changed_by_name' => $audit->changedByUser?->name ?? '—',
            ];
        });

        $data = $this->resolveAuditDisplayValues($data);

        return response()->json(['data' => $data]);
    }

    /**
     * POST add attachments to an existing submission. Accepts multipart with files (documents[] or document_1, document_2, ...).
     */
    public function addAttachments(Request $request, CustomerSupportSubmission $customerSupportSubmission): JsonResponse
    {
        $this->authorize('update', $customerSupportSubmission);

        $request->validate([
            'documents' => ['required', 'array', 'max:10'],
            'documents.*' => ['file', 'max:10240', new AllowedDocumentFile()],
        ]);

        $files = $request->allFiles();
        $newEntries = [];
        $dir = 'customer-support/' . $customerSupportSubmission->id;

        foreach ($files as $key => $uploaded) {
            if (is_array($uploaded)) {
                foreach ($uploaded as $f) {
                    if ($f && $f->isValid()) {
                        $path = $f->store($dir, 'public');
                        $newEntries[] = ['path' => $path, 'file_name' => $f->getClientOriginalName()];
                    }
                }
            } elseif ($uploaded && $uploaded->isValid()) {
                $path = $uploaded->store($dir, 'public');
                $newEntries[] = ['path' => $path, 'file_name' => $uploaded->getClientOriginalName()];
            }
        }

        if (count($newEntries) > 0) {
            $current = $customerSupportSubmission->attachments ?? [];
            $customerSupportSubmission->update(['attachments' => array_merge($current, $newEntries)]);
        }

        return response()->json([
            'message' => count($newEntries) . ' file(s) added.',
            'attachments' => $customerSupportSubmission->fresh()->attachments,
        ]);
    }

    /**
     * DELETE /api/customer-support/{customerSupportSubmission}/attachments/{index}
     * Remove an attachment by zero-based index.
     */
    public function removeAttachment(Request $request, CustomerSupportSubmission $customerSupportSubmission, int $index): JsonResponse
    {
        $this->authorize('update', $customerSupportSubmission);

        $attachments = $customerSupportSubmission->attachments;
        if (! is_array($attachments) || ! isset($attachments[$index])) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $entry = $attachments[$index];
        $path = is_array($entry) ? ($entry['path'] ?? null) : null;
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        array_splice($attachments, $index, 1);
        $customerSupportSubmission->update(['attachments' => array_values($attachments)]);

        return response()->json([
            'message' => 'Document removed.',
            'attachments' => $customerSupportSubmission->fresh()->attachments ?? [],
        ]);
    }

    /**
     * Download an attachment by index (0 or 1). Returns file stream or 404.
     */
    public function downloadAttachment(CustomerSupportSubmission $customerSupportSubmission, int $index): Response|JsonResponse
    {
        $this->authorize('view', $customerSupportSubmission);

        $attachments = $customerSupportSubmission->attachments;
        if (! is_array($attachments) || ! isset($attachments[$index])) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $att = $attachments[$index];
        $path = $att['path'] ?? null;
        if (! $path || ! is_string($path)) {
            return response()->json(['message' => 'Attachment file not found.'], 404);
        }

        $fullPath = Storage::disk('public')->path($path);
        if (! is_file($fullPath)) {
            return response()->json(['message' => 'File not found on disk.'], 404);
        }

        $filename = $att['file_name'] ?? $att['original_name'] ?? basename($path);

        return response()->file($fullPath, [
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * GET /api/customer-support/csr-options
     * Returns list of CSR users for the assign modal.
     * Accessible by superadmin, CSR, support_manager, or any user who can view CS submissions.
     */
    public function csrOptions(Request $request): JsonResponse
    {
        $this->authorize('assignAny', CustomerSupportSubmission::class);

        $csrs = Cache::remember('csr_options', 600, function () {
            return DB::table('users')
                ->join('model_has_roles', function ($j) {
                    $j->on('users.id', '=', 'model_has_roles.model_id')
                      ->where('model_has_roles.model_type', (new \App\Models\User)->getMorphClass());
                })
                ->join('roles', function ($j) {
                    $j->on('model_has_roles.role_id', '=', 'roles.id')
                      ->whereIn('roles.name', ['customer_support_representative', 'csr', 'support_manager']);
                })
                ->where('users.status', 'approved')
                ->orderBy('users.name')
                ->select('users.id', 'users.name')
                ->get()
                ->all();
        });

        return response()->json([
            'csrs' => $csrs,
        ]);
    }

    /**
     * GET /api/customer-support/csrs-by-account?account_number=...
     * Returns CSR user IDs assigned to a client with the given account number via client_csrs.
     */
    public function csrsByAccount(Request $request): JsonResponse
    {
        $accountNumber = $request->query('account_number');
        if (! $accountNumber) {
            return response()->json(['csr_ids' => []]);
        }

        $client = Client::where('account_number', $accountNumber)->first();
        if (! $client) {
            return response()->json(['csr_ids' => []]);
        }

        $csrIds = DB::table('client_csrs')
            ->where('client_id', $client->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return response()->json(['csr_ids' => $csrIds]);
    }

    /**
     * POST /api/customer-support/bulk-assign
     * Dispatches a queue job for instant response. Returns a tracking_id for progress polling.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $this->authorize('assignAny', CustomerSupportSubmission::class);
        $user = $request->user();

        $data = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['integer'],
            'csr_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ids = array_unique(array_map('intval', $data['submission_ids']));
        $csrId = (int) $data['csr_id'];
        $csrUser = User::find($csrId);

        if (! $csrUser || ! CustomerSupportSubmissionPolicy::isValidAssignee($csrUser)) {
            return response()->json([
                'message' => 'Selected assignee must be a CSR or support manager.',
            ], 422);
        }

        $existingCount = DB::table('customer_support_submissions')->whereIn('id', $ids)->count();
        if ($existingCount !== count($ids)) {
            return response()->json(['message' => 'One or more submission IDs are invalid.'], 422);
        }

        $trackingId = (string) Str::uuid();

        Cache::put("bulk_assign:{$trackingId}", [
            'status' => 'pending',
            'total' => count($ids),
            'processed' => 0,
            'percent' => 0,
            'message' => 'Queued for processing...',
        ], now()->addMinutes(30));

        BulkAssignCsrJob::dispatch($ids, $csrId, $user->id, $trackingId);

        return response()->json([
            'message' => 'Assignment queued. Processing '.count($ids).' submission(s) in background.',
            'queued' => true,
            'count' => count($ids),
            'tracking_id' => $trackingId,
        ]);
    }

    /**
     * GET /api/customer-support/bulk-assign/{trackingId}/status
     */
    public function bulkAssignStatus(Request $request, string $trackingId): JsonResponse
    {
        $data = Cache::get("bulk_assign:{$trackingId}");

        if (! $data) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json($data);
    }

    /**
     * PATCH /api/customer-support/{id}/assign-csr
     * Assign a CSR to a single customer support submission.
     */
    public function assignCsr(Request $request, CustomerSupportSubmission $customerSupportSubmission): JsonResponse
    {
        $this->authorize('assign', $customerSupportSubmission);

        $data = $request->validate([
            'csr_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $csrUser = User::find($data['csr_id']);
        if (! $csrUser || ! CustomerSupportSubmissionPolicy::isValidAssignee($csrUser)) {
            return response()->json([
                'message' => 'Selected assignee must be a CSR or support manager.',
            ], 422);
        }

        $customerSupportSubmission->update([
            'csr_id' => $data['csr_id'],
            'csr_name' => $csrUser ? $csrUser->name : null,
        ]);

        return response()->json([
            'id' => $customerSupportSubmission->id,
            'message' => 'CSR assigned.',
            'csr_id' => $data['csr_id'],
            'csr_name' => $csrUser?->name,
        ]);
    }

    /**
     * Aggregated bootstrap: filters + columns + team/CSR options + first-page data in one request.
     * Eliminates 5+ sequential API calls (filters, columns, team-options, csr-options, index).
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $filtersResponse = $this->filters($request);
        $filtersData = json_decode($filtersResponse->getContent(), true);

        $columnsResponse = $this->columns($request);
        $columnsData = json_decode($columnsResponse->getContent(), true);

        $indexResponse = $this->index($request);
        $indexData = json_decode($indexResponse->getContent(), true);

        // Include team options (cached in FieldSubmissionController for 5 min)
        $teamData = [];
        try {
            $teamResponse = app(FieldSubmissionController::class)->teamOptions($request);
            $teamData = json_decode($teamResponse->getContent(), true) ?? [];
        } catch (\Throwable $e) {
            // silent - team options are optional for listing
        }

        // Include CSR options (for assign modal) — use same cache as csrOptions()
        $csrData = [];
        $user = $request->user();
        $canAssign = $user->can('assignAny', CustomerSupportSubmission::class);

        if ($canAssign) {
            try {
                $csrs = Cache::remember('csr_options', 600, function () {
                    return DB::table('users')
                        ->join('model_has_roles', function ($j) {
                            $j->on('users.id', '=', 'model_has_roles.model_id')
                              ->where('model_has_roles.model_type', (new User)->getMorphClass());
                        })
                        ->join('roles', function ($j) {
                            $j->on('model_has_roles.role_id', '=', 'roles.id')
                              ->whereIn('roles.name', ['customer_support_representative', 'csr', 'support_manager']);
                        })
                        ->where('users.status', 'approved')
                        ->orderBy('users.name')
                        ->select('users.id', 'users.name')
                        ->get()
                        ->all();
                });

                $csrData = ['csrs' => $csrs];
            } catch (\Throwable $e) {
                // silent
            }
        }

        return response()->json([
            'filters' => $filtersData,
            'columns' => $columnsData,
            'page' => $indexData,
            'team_options' => $teamData,
            'csr_options' => $csrData,
        ]);
    }
}
