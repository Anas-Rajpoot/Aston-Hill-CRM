<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerSupportController;
use App\Http\Controllers\FieldSubmissionController;
use App\Models\CustomerSupportSubmission;
use App\Models\CustomerSupportSubmissionAudit;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class CustomerSupportApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;
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

        $customerSupportSubmission->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'creator:id,name', 'creator.roles:id,name']);
        $row = $customerSupportSubmission->toArray();
        $row['manager_id'] = $customerSupportSubmission->manager_id;
        $row['team_leader_id'] = $customerSupportSubmission->team_leader_id;
        $row['sales_agent_id'] = $customerSupportSubmission->sales_agent_id;
        $row['manager_name'] = $customerSupportSubmission->manager?->name;
        $row['team_leader_name'] = $customerSupportSubmission->teamLeader?->name;
        $row['sales_agent_name'] = $customerSupportSubmission->salesAgent?->name;
        $row['creator_name'] = $customerSupportSubmission->creator?->name;
        $firstRole = $customerSupportSubmission->creator?->roles->first();
        $row['creator_role'] = $firstRole
            ? ucfirst(str_replace('_', ' ', $firstRole->name))
            : 'Customer Support Representative';
        $row['submitted_at'] = $customerSupportSubmission->submitted_at?->toIso8601String();
        $row['created_at'] = $customerSupportSubmission->created_at?->toIso8601String();
        $row['completion_date'] = $customerSupportSubmission->completion_date?->format('Y-m-d');

        return response()->json($row);
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
                'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)], CustomerSupportSubmission::STATUSES),
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
            'issue_category' => ['required', 'string', Rule::in($categories)],
            'company_name' => ['required', 'string', 'max:255'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:50'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'manager_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'team_leader_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'sales_agent_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(CustomerSupportSubmission::STATUSES)],
            'ticket_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'team_leader_id.required' => 'Please select a team leader.',
            'team_leader_id.min' => 'Please select a team leader.',
            'sales_agent_id.required' => 'Please select a sales agent.',
            'sales_agent_id.min' => 'Please select a sales agent.',
        ];

        $data = $request->validate($rules, $messages);
        if (! empty($data)) {
            if (isset($data['status']) && $data['status'] === 'submitted' && ! $customerSupportSubmission->submitted_at) {
                $data['submitted_at'] = now();
            }
            // Do not set manager_id, team_leader_id, sales_agent_id to null (columns are NOT NULL)
            foreach (['manager_id', 'team_leader_id', 'sales_agent_id'] as $key) {
                if (array_key_exists($key, $data) && $data[$key] === null) {
                    unset($data[$key]);
                }
            }
            $customerSupportSubmission->update($data);
            // Update history is tracked by CustomerSupportSubmissionObserver -> customer_support_submission_audits
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

    /**
     * GET edit form options: issue categories, workflow statuses, pending options.
     */
    public function editOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSupportSubmission::class);

        return response()->json([
            'issue_categories' => CustomerSupportController::issueCategories(),
            'workflow_statuses' => array_map(fn ($v) => ['value' => $v, 'label' => ucfirst(str_replace('_', ' ', $v))], CustomerSupportSubmission::WORKFLOW_STATUSES),
            'pending_options' => array_map(fn ($v) => ['value' => $v, 'label' => $v], CustomerSupportSubmission::PENDING_OPTIONS),
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
        $user = $request->user();
        $allowed = $user->roles()->whereIn('name', ['superadmin', 'customer_support_representative', 'support_manager', 'manager', 'team_leader'])->exists()
            || $user->can('viewAny', CustomerSupportSubmission::class);
        if (! $allowed) {
            abort(403, 'Unauthorized.');
        }

        $csrs = User::whereHas('roles', fn ($q) => $q->where('name', 'customer_support_representative'))
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);

        return response()->json([
            'csrs' => $csrs->values()->all(),
        ]);
    }

    /**
     * POST /api/customer-support/bulk-assign
     * Assign one CSR to multiple customer support submissions.
     * Only superadmin, support_manager, or customer_support_representative role.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('superadmin') && ! $user->hasRole('customer_support_representative') && ! $user->hasRole('support_manager')) {
            abort(403, 'Only superadmin, support manager, or CSR can bulk assign.');
        }

        $data = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['integer', 'exists:customer_support_submissions,id'],
            'csr_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ids = $data['submission_ids'];
        $csrId = $data['csr_id'];

        // Get the CSR user name for the csr_name field
        $csrUser = User::find($csrId);
        $csrName = $csrUser ? $csrUser->name : null;

        $updated = CustomerSupportSubmission::query()
            ->whereIn('id', $ids)
            ->visibleTo($user)
            ->update([
                'csr_id' => $csrId,
                'csr_name' => $csrName,
            ]);

        try {
            SystemAuditLog::record(
                'customer_support.bulk_assigned',
                null,
                ['submission_ids' => $ids, 'assigned_to' => $csrId, 'count' => count($ids)],
                $user->id,
                'customer_support'
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => "Assigned {$updated} request(s) to CSR.",
            'updated_count' => $updated,
        ]);
    }

    /**
     * PATCH /api/customer-support/{id}/assign-csr
     * Assign a CSR to a single customer support submission.
     */
    public function assignCsr(Request $request, CustomerSupportSubmission $customerSupportSubmission): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('superadmin') && ! $user->hasRole('customer_support_representative') && ! $user->hasRole('support_manager')) {
            abort(403, 'Unauthorized.');
        }

        $data = $request->validate([
            'csr_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $csrUser = User::find($data['csr_id']);

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

        // Include CSR options (for assign modal)
        $csrData = [];
        $user = $request->user();
        if ($user->hasRole('superadmin') || $user->hasRole('customer_support_representative') || $user->hasRole('support_manager')) {
            try {
                $csrs = User::whereHas('roles', fn ($q) => $q->where('name', 'customer_support_representative'))
                    ->where('status', 'approved')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);

                $csrData = [
                    'csrs' => $csrs->values()->all(),
                ];
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
