<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FieldSubmissionController;
use App\Http\Controllers\VasRequestController;
use App\Jobs\BulkAssignVasJob;
use App\Models\SlaRule;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserColumnPreference;
use App\Models\VasRequestAudit;
use App\Models\VasRequestSubmission;
use App\Policies\VasRequestPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class VasRequestApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;
    private const MODULE = 'vas_request_submissions';

    private const ALLOWED_COLUMNS = [
        'id', 'created_at', 'submitted_at', 'created_by', 'updated_at', 'approved_at', 'rejected_at',
        'request_type', 'account_number', 'contact_number', 'company_name', 'request_description', 'description', 'additional_notes',
        'manager_id', 'team_leader_id', 'sales_agent_id', 'back_office_executive_id',
        'status', 'sla_timer', 'activity', 'completion_date', 'remarks',
    ];

    private const BASE_COLUMNS = ['id', 'status'];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', VasRequestSubmission::class);
        } catch (Throwable $e) {
            Log::warning('VasRequestApiController@index authorize', ['exception' => $e->getMessage()]);
            throw $e;
        }

        try {
            return $this->indexResponse($request);
        } catch (Throwable $e) {
            Log::error('VasRequestApiController@index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Error loading VAS requests.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function indexResponse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'executive', 'creator']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'request_type' => ['sometimes', 'nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(VasRequestSubmission::STATUSES)],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'back_office_executive_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'card_filter' => ['sometimes', 'nullable', 'string', Rule::in(['all', 'pending', 'completed_today'])],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';

        // Single data query with sort, select, eager load, then paginate.
        $dataQuery = VasRequestSubmission::query()->visibleTo($user);
        $this->applyFilters($dataQuery, $validated);
        $this->applyCardFilter($dataQuery, $validated['card_filter'] ?? null);
        $this->applySort($dataQuery, $sort, $order);

        $selectColumns = $this->buildSelectColumns($columns);
        $dataQuery->select($selectColumns);

        $eagerLoad = [];
        if (in_array('creator', $columns, true)) {
            $eagerLoad['creator'] = fn ($q) => $q->select('id', 'name', 'email');
        }
        if (in_array('manager', $columns, true) || in_array('team_leader', $columns, true) || in_array('sales_agent', $columns, true) || in_array('executive', $columns, true)) {
            $eagerLoad['manager'] = fn ($q) => $q->select('id', 'name');
            $eagerLoad['teamLeader'] = fn ($q) => $q->select('id', 'name');
            $eagerLoad['salesAgent'] = fn ($q) => $q->select('id', 'name');
            $eagerLoad['backOfficeExecutive'] = fn ($q) => $q->select('id', 'name');
        }
        if (! empty($eagerLoad)) {
            $dataQuery->with($eagerLoad);
        }

        $offset = ($page - 1) * $perPage;
        $items = $dataQuery->skip($offset)->take($perPage)->get()->map(function ($row) use ($columns) {
            return $this->formatRow($row, $columns);
        });

        // Cache the count for 30s to avoid expensive COUNT(*) on every paginate/filter.
        $countCacheKey = 'vas_count_' . $user->id . '_' . md5(json_encode($validated));
        $total = Cache::remember($countCacheKey, 30, function () use ($user, $validated) {
            $cq = VasRequestSubmission::query()->visibleTo($user);
            $this->applyFilters($cq, $validated);
            $this->applyCardFilter($cq, $validated['card_filter'] ?? null);
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

    private function resolveColumns($user, ?array $requestColumns): array
    {
        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'executive', 'creator']);
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

        $cols = $preference?->visible_columns ?? config('modules.vas_request_submissions.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        $allowed = array_intersect($cols, $allAllowed);
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function applyFilters($query, array $validated): void
    {
        if (! empty($validated['status'])) {
            if ($validated['status'] === 'unassigned') {
                $query->whereIn('status', ['unassigned', 'draft', 'pending_with_csr', 'pending_with_du', 'pending_with_sales', 'pending_for_approval']);
            } elseif ($validated['status'] === 'completed') {
                $query->whereIn('status', ['completed', 'approved']);
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
        if (! empty($validated['request_type'])) {
            $query->where('request_type', $validated['request_type']);
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
        if (! empty($validated['back_office_executive_id'])) {
            $query->where('back_office_executive_id', $validated['back_office_executive_id']);
        }
        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'like', $term)
                    ->orWhere('account_number', 'like', $term)
                    ->orWhere('request_description', 'like', $term);
            });
        }
    }

    /**
     * Scope VAS queries by user role.
     * Super admin / vas.view.all → all records.
     * Back office → assigned to them.
     * Others → created by them or assigned as sales agent / team leader / manager.
     */
    /**
     * Apply card-level quick filter (from KPI card clicks).
     */
    private function applyCardFilter($query, ?string $cardFilter): void
    {
        if (! $cardFilter || $cardFilter === 'all') {
            return;
        }

        if ($cardFilter === 'pending') {
            $query->whereIn('status', ['draft', 'submitted_under_process', 'unassigned', 'pending_with_csr', 'pending_with_du', 'pending_with_sales', 'pending_for_approval']);
        } elseif ($cardFilter === 'completed_today') {
            $todayStart = now()->startOfDay()->toDateTimeString();
            $todayEnd = now()->endOfDay()->toDateTimeString();
            $query->whereIn('status', ['completed', 'approved'])->whereBetween('updated_at', [$todayStart, $todayEnd]);
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        $direction = strtolower($order) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'creator') {
            $query->leftJoin('users as creator_users', 'vas_request_submissions.created_by', '=', 'creator_users.id')
                ->orderBy('creator_users.name', $direction);
            return;
        }
        if ($sort === 'sla_timer') {
            $query->orderByRaw("COALESCE(vas_request_submissions.submitted_at, vas_request_submissions.created_at) {$direction}");
            return;
        }
        if (in_array($sort, ['manager', 'team_leader', 'sales_agent', 'executive'], true)) {
            $col = $sort === 'executive' ? 'back_office_executive_id' : $sort . '_id';
            $alias = $sort . '_users';
            $query->leftJoin("users as {$alias}", "vas_request_submissions.{$col}", '=', "{$alias}.id")
                ->orderBy("{$alias}.name", $direction);
            return;
        }
        $query->orderBy('vas_request_submissions.' . $sort, $direction);
    }

    private function buildSelectColumns(array $columns): array
    {
        $t = 'vas_request_submissions';
        $base = ["{$t}.id", "{$t}.status"];
        $map = [
            'created_at' => "{$t}.created_at",
            'submitted_at' => "{$t}.submitted_at",
            'created_by' => "{$t}.created_by",
            'creator' => "{$t}.created_by",
            'request_type' => "{$t}.request_type",
            'account_number' => "{$t}.account_number",
            'contact_number' => "{$t}.contact_number",
            'company_name' => "{$t}.company_name",
            'description' => "{$t}.description",
            'request_description' => "{$t}.description",
            'additional_notes' => "{$t}.additional_notes",
            'manager_id' => "{$t}.manager_id",
            'manager' => "{$t}.manager_id",
            'team_leader_id' => "{$t}.team_leader_id",
            'team_leader' => "{$t}.team_leader_id",
            'sales_agent_id' => "{$t}.sales_agent_id",
            'sales_agent' => "{$t}.sales_agent_id",
            'back_office_executive_id' => "{$t}.back_office_executive_id",
            'executive' => "{$t}.back_office_executive_id",
            'approved_at' => "{$t}.approved_at",
            'rejected_at' => "{$t}.rejected_at",
            'updated_at' => "{$t}.updated_at",
            'activity' => "{$t}.activity",
            'completion_date' => "{$t}.completion_date",
            'remarks' => "{$t}.remarks",
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
            $base[] = "{$t}.created_at";
            $base[] = "{$t}.submitted_at";
            $base[] = "{$t}.back_office_executive_id";
        }
        $qualified = array_unique($base);
        // Alias qualified columns so Eloquent hydrates model attributes correctly (id, status, etc.)
        $aliased = [];
        foreach ($qualified as $q) {
            $aliased[] = strpos($q, '.') !== false ? $q . ' as ' . substr($q, strrpos($q, '.') + 1) : $q;
        }
        return $aliased;
    }

    private function formatRow(VasRequestSubmission $row, array $columns): array
    {
        $out = [];
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
            if ($col === 'executive') {
                $out['back_office_executive_id'] = $row->back_office_executive_id;
                $out['executive'] = $row->relationLoaded('backOfficeExecutive') ? ($row->backOfficeExecutive?->name ?? null) : null;
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
            if (in_array($col, ['created_at', 'approved_at', 'rejected_at', 'updated_at'], true)) {
                $out[$col] = $row->$col ? $row->$col->format('d-M-Y H:i') : null;
                continue;
            }
            if ($col === 'submitted_at') {
                $submissionDate = $row->submitted_at ?? $row->created_at;
                $out['submitted_at'] = $submissionDate ? $submissionDate->format('d-M-Y H:i') : null;
                continue;
            }
            if ($col === 'completion_date') {
                $out[$col] = $row->completion_date ? $row->completion_date->format('d-M-Y') : null;
                continue;
            }
            if ($col === 'request_description') {
                $out['request_description'] = $row->description ?? null;
                continue;
            }
            if ($col === 'status') {
                $status = (string) ($row->status ?? '');
                if (in_array($status, ['draft', 'pending_with_csr', 'pending_with_du', 'pending_with_sales', 'pending_for_approval'], true)) {
                    $status = 'unassigned';
                } elseif ($status === 'approved') {
                    $status = 'completed';
                }
                $out['status'] = $status;
                continue;
            }
            $out[$col] = $row->$col ?? null;
        }
        return $out;
    }

    private function computeSlaTimer(VasRequestSubmission $row): ?string
    {
        $startAt = $row->submitted_at ?? $row->created_at;
        if (! $startAt) {
            return null;
        }
        if (! empty($row->back_office_executive_id)) {
            return 'Assigned';
        }

        $rule = SlaRule::cached()->firstWhere('module_key', 'vas_requests');
        $slaMinutes = ($rule && $rule->is_active)
            ? max(1, (int) $rule->sla_duration_minutes)
            : 960;
        $warningMinutes = ($rule && $rule->is_active)
            ? max(0, (int) $rule->warning_threshold_minutes)
            : 90;

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
        $this->authorize('viewAny', VasRequestSubmission::class);

        $userId = request()->user()->id;
        $data = \App\Services\SubmissionCacheService::rememberMeta('vas', 'filters', $userId, function () {
            $requestTypes = VasRequestController::requestTypes();
            $types = array_map(fn ($t) => ['value' => $t, 'label' => $t], $requestTypes);

            $teamOptions = app(FieldSubmissionController::class)->teamOptions(request());
            $teamData = $teamOptions->getData(true);
            $managers = $teamData['managers'] ?? [];
            $teamLeaders = $teamData['team_leaders'] ?? [];
            $salesAgents = $teamData['sales_agents'] ?? [];

            return [
                'request_types' => array_values($types),
                'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => $s === 'unassigned' ? 'UnAssigned' : ucwords(str_replace('_', ' ', $s))], VasRequestSubmission::STATUSES),
                'managers' => $managers,
                'team_leaders' => $teamLeaders,
                'sales_agents' => $salesAgents,
            ];
        });

        return response()->json($data);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VasRequestSubmission::class);

        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'executive', 'creator']);

        $config = config('modules.vas_request_submissions.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            if (! in_array($key, $allAllowed, true)) continue;
            $allColumns[] = [
                'key' => $key,
                'label' => $def['label'] ?? $key,
            ];
        }

        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', self::MODULE)
            ->first();

        $visible = $pref?->visible_columns ?? config('modules.vas_request_submissions.default_columns', []);
        $visible = array_values(array_intersect($visible, $allAllowed));

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VasRequestSubmission::class);

        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'executive', 'creator']);
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

    public function patch(Request $request, VasRequestSubmission $vasRequest): JsonResponse
    {
        $isAssignmentOnly = $request->exists('back_office_executive_id')
            && count(array_diff(array_keys($request->all()), ['back_office_executive_id'])) === 0;
        if ($request->exists('back_office_executive_id')) {
            $this->authorize('assign', $vasRequest);
        }
        if (! $isAssignmentOnly) {
            $this->authorize('update', $vasRequest);
        }

        $types = VasRequestController::requestTypes();
        $rules = [
            'request_type' => ['sometimes', 'string', Rule::in($types)],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'manager_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'back_office_executive_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(VasRequestSubmission::STATUSES)],
        ];

        $data = $request->validate($rules);
        if (array_key_exists('back_office_executive_id', $data) && ! empty($data['back_office_executive_id'])) {
            $assignee = User::find((int) $data['back_office_executive_id']);
            if (! $assignee || ! VasRequestPolicy::isValidAssignee($assignee)) {
                return response()->json([
                    'message' => 'Selected assignee must be a back office user.',
                ], 422);
            }
        }
        if (! empty($data)) {
            $vasRequest->update($data);
        }

        $vasRequest->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'backOfficeExecutive:id,name', 'creator:id,name']);
        $columns = array_merge(
            ['id', 'created_at', 'request_type', 'account_number', 'company_name', 'description', 'approved_at', 'manager', 'team_leader', 'sales_agent', 'executive', 'status', 'creator'],
            array_keys($data)
        );
        $columns = array_unique($columns);
        $row = $this->formatRow($vasRequest, $columns);

        return response()->json([
            'id' => $vasRequest->id,
            'message' => 'Updated.',
            'row' => $row,
        ]);
    }

    public function destroy(Request $request, VasRequestSubmission $vasRequest): JsonResponse
    {
        $this->authorize('delete', $vasRequest);

        $requestId = (int) $vasRequest->id;
        $vasRequest->delete();

        Storage::disk('public')->deleteDirectory("vas-requests/{$requestId}");

        SystemAuditLog::record('vas_request.deleted', [
            'vas_request_id' => $requestId,
        ], null, $request->user()->id, 'vas_request', $requestId);

        return response()->json(['message' => 'VAS request deleted.']);
    }

    /**
     * GET /api/vas-requests/back-office-options
     * Same back office executives list as lead submissions (for assign modal).
     * Accessible by superadmin, back_office, or any user who can view VAS requests.
     */
    public function backOfficeOptions(Request $request): JsonResponse
    {
        $this->authorize('assignAny', VasRequestSubmission::class);

        $executives = Cache::remember('vas_back_office_options', 600, function () {
            return DB::table('users')
                ->join('model_has_roles', function ($j) {
                    $j->on('users.id', '=', 'model_has_roles.model_id')
                      ->where('model_has_roles.model_type', (new \App\Models\User)->getMorphClass());
                })
                ->join('roles', function ($j) {
                    $j->on('model_has_roles.role_id', '=', 'roles.id')
                      ->whereIn('roles.name', ['back_office', 'backoffice', 'back_office_executive']);
                })
                ->where('users.status', 'approved')
                ->orderBy('users.name')
                ->select('users.id', 'users.name')
                ->get()
                ->all();
        });

        return response()->json([
            'executives' => $executives,
        ]);
    }

    /**
     * POST /api/vas-requests/bulk-assign
     * Dispatches a queue job for instant response. Returns a tracking_id for progress polling.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $this->authorize('assignAny', VasRequestSubmission::class);
        $user = $request->user();

        $data = $request->validate([
            'vas_request_ids' => ['required', 'array', 'min:1'],
            'vas_request_ids.*' => ['integer'],
            'executive_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ids = array_unique(array_map('intval', $data['vas_request_ids']));
        $executiveId = (int) $data['executive_id'];
        $executive = User::find($executiveId);

        if (! $executive || ! VasRequestPolicy::isValidAssignee($executive)) {
            return response()->json([
                'message' => 'Selected assignee must be a back office user.',
            ], 422);
        }

        $existingCount = DB::table('vas_request_submissions')->whereIn('id', $ids)->count();
        if ($existingCount !== count($ids)) {
            return response()->json(['message' => 'One or more request IDs are invalid.'], 422);
        }

        $trackingId = (string) Str::uuid();

        Cache::put("bulk_assign:{$trackingId}", [
            'status' => 'pending',
            'total' => count($ids),
            'processed' => 0,
            'percent' => 0,
            'message' => 'Queued for processing...',
        ], now()->addMinutes(30));

        BulkAssignVasJob::dispatchSync($ids, $executiveId, $user->id, $trackingId);

        return response()->json([
            'message' => 'Assignment completed. '.count($ids).' request(s) assigned.',
            'queued' => true,
            'count' => count($ids),
            'tracking_id' => $trackingId,
        ]);
    }

    /**
     * GET /api/vas-requests/bulk-assign/{trackingId}/status
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
     * GET /api/vas-requests/{vasRequest}/audits
     * Change history for a VAS request: field, old value, new value, date/time, who.
     */
    public function audits(Request $request, VasRequestSubmission $vasRequest): JsonResponse
    {
        $this->authorize('view', $vasRequest);

        $rows = VasRequestAudit::query()
            ->where('vas_request_submission_id', $vasRequest->id)
            ->with('user:id,name')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $data = $rows->map(function (VasRequestAudit $audit) {
            return [
                'id' => $audit->id,
                'field_name' => $audit->field_name,
                'old_value' => $audit->old_value,
                'new_value' => $audit->new_value,
                'changed_at' => $audit->changed_at?->toIso8601String(),
                'changed_by' => $audit->changed_by,
                'changed_by_name' => $audit->user?->name ?? '—',
            ];
        });

        $data = $this->resolveAuditDisplayValues($data);

        return response()->json(['data' => $data]);
    }

    /**
     * Aggregated bootstrap: filters + columns + team/BO options + first-page data in one request.
     * Eliminates 5+ sequential API calls (filters, columns, team-options, back-office-options, index).
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

        // Include back office options (for assign modal) — use same cache as backOfficeOptions()
        $boData = [];
        $user = $request->user();
        $canAssign = $user->can('assignAny', VasRequestSubmission::class);

        if ($canAssign) {
            try {
                $executives = Cache::remember('vas_back_office_options', 600, function () {
                    return DB::table('users')
                        ->join('model_has_roles', function ($j) {
                            $j->on('users.id', '=', 'model_has_roles.model_id')
                              ->where('model_has_roles.model_type', (new User)->getMorphClass());
                        })
                        ->join('roles', function ($j) {
                            $j->on('model_has_roles.role_id', '=', 'roles.id')
                              ->whereIn('roles.name', ['back_office', 'backoffice', 'back_office_executive']);
                        })
                        ->where('users.status', 'approved')
                        ->orderBy('users.name')
                        ->select('users.id', 'users.name')
                        ->get()
                        ->all();
                });

                $boData = ['executives' => $executives];
            } catch (\Throwable $e) {
                // silent
            }
        }

        return response()->json([
            'filters' => $filtersData,
            'columns' => $columnsData,
            'page' => $indexData,
            'team_options' => $teamData,
            'back_office_options' => $boData,
        ]);
    }
}
