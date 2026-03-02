<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadSubmission;
use App\Models\LeadSubmissionAudit;
use App\Models\SlaRule;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserColumnPreference;
use App\Rules\AllowedDocumentFile;
use App\Jobs\BulkAssignLeadJob;
use App\Policies\LeadSubmissionPolicy;
use App\Services\LeadSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Optimized Lead Submissions Listing API.
 * Target: < 1s response time. Uses scopes, eager loading, column selection, cached preferences.
 */
class LeadSubmissionApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;
    private const MODULE = 'lead_submissions';

    /** Whitelist of allowed columns for SELECT (prevents SQL injection / unauthorized exposure). */
    private const ALLOWED_COLUMNS = [
        'id', 'company_name', 'account_number', 'authorized_signatory_name', 'email', 'contact_number_gsm',
        'alternate_contact_number', 'address', 'emirate', 'location_coordinates',
        'service_category_id', 'service_type_id', 'status', 'status_changed_at',
        'submitted_at', 'updated_at', 'created_by', 'product', 'offer', 'mrc_aed', 'quantity',
        'ae_domain', 'gaid', 'remarks', 'submission_type',
        'call_verification', 'pending_from_sales', 'documents_verification', 'submission_date_from',
        'back_office_notes', 'activity', 'back_office_account', 'work_order', 'du_status', 'completion_date',
        'du_remarks', 'additional_note',
    ];

    /** Base columns required for every row (id for links, status for inline edit, created_by for resubmit). */
    private const BASE_COLUMNS = ['id', 'status', 'created_by'];

    public function __construct(private LeadSubmissionService $leadSubmissionService)
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * GET /api/lead-submissions
     * Pagination, sorting, filtering, column selection, permission-based visibility.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeadSubmission::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager', 'executive', 'submission_type', 'sla_timer']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager', 'executive', 'submission_type', 'sla_timer']))],
            'submission_type' => ['sometimes', 'nullable', 'string', Rule::in(['new', 'resubmission'])],
            'service_type_id' => ['sometimes', 'nullable', 'integer', 'exists:service_types,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(LeadSubmission::STATUSES)],
            'service_category_id' => ['sometimes', 'nullable', 'integer', 'exists:service_categories,id'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'product' => ['sometimes', 'nullable', 'string', 'max:150'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'updated_from' => ['sometimes', 'nullable', 'date'],
            'updated_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:updated_from'],
            'mrc' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'submitted_at';
        $order = $validated['order'] ?? 'desc';

        // Single data query with sort, select, eager load, then paginate.
        $dataQuery = LeadSubmission::query()->visibleTo($user);
        $this->applyFilters($dataQuery, $validated);
        $this->applySort($dataQuery, $sort, $order);

        $selectColumns = $this->buildSelectColumns($columns);
        $dataQuery->select($selectColumns);

        $eagerLoad = [];
        if (in_array('creator', $columns, true)) {
            $eagerLoad['creator'] = fn ($q) => $q->select('id', 'name', 'email');
        }
        if (in_array('type', $columns, true)) {
            $eagerLoad['type'] = fn ($q) => $q->select('id', 'name');
        }
        if (in_array('category', $columns, true)) {
            $eagerLoad['category'] = fn ($q) => $q->select('id', 'name');
        }
        if (in_array('sales_agent', $columns, true) || in_array('team_leader', $columns, true) || in_array('manager', $columns, true)) {
            foreach (['salesAgent', 'teamLeader', 'manager'] as $rel) {
                $eagerLoad[$rel] = fn ($q) => $q->select('id', 'name');
            }
        }
        if (in_array('executive', $columns, true)) {
            $eagerLoad['executive'] = fn ($q) => $q->select('id', 'name');
        }
        if (!empty($eagerLoad)) {
            $dataQuery->with($eagerLoad);
        }

        $offset = ($page - 1) * $perPage;
        $leads = $dataQuery->skip($offset)->take($perPage)->get();

        // Cache the count for 30s to avoid expensive COUNT(*) on every paginate/filter.
        // Build a cache key from user + filter params so it varies correctly.
        $countCacheKey = 'lead_count_' . $user->id . '_' . md5(json_encode($validated));
        $total = Cache::remember($countCacheKey, 30, function () use ($user, $validated) {
            $cq = LeadSubmission::query()->visibleTo($user);
            $this->applyFilters($cq, $validated);
            return $cq->count();
        });

        $items = $leads->map(function ($lead) use ($columns) {
            return $this->formatLeadRow($lead, $columns);
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

    /** Resolve visible columns: from request, cached preference, or config default. */
    private function resolveColumns($user, ?array $requestColumns): array
    {
        if (!empty($requestColumns)) {
            $allowed = array_intersect($requestColumns, array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager', 'executive', 'submission_type', 'sla_timer']));
            return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
        }

        $cacheKey = "col_pref_{$user->id}_" . self::MODULE;
        $preference = Cache::remember($cacheKey, 3600, function () use ($user) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', self::MODULE)
                ->first();
        });

        $cols = $preference?->visible_columns ?? config('modules.lead_submissions.default_columns', []);
        $cols = is_array($cols) ? $cols : [];

        $allowed = array_intersect($cols, array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager', 'executive', 'submission_type', 'sla_timer']));
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function applyFilters($query, array $validated): void
    {
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['submission_type'])) {
            $query->where('submission_type', $validated['submission_type']);
        }
        if (!empty($validated['service_category_id'])) {
            $query->where('service_category_id', $validated['service_category_id']);
        }
        if (!empty($validated['service_type_id'])) {
            $query->where('service_type_id', $validated['service_type_id']);
        }
        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from'] . ' 00:00:00');
        }
        if (!empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }
        if (!empty($validated['submitted_from'])) {
            $query->where('submitted_at', '>=', $validated['submitted_from'] . ' 00:00:00');
        }
        if (!empty($validated['submitted_to'])) {
            $query->where('submitted_at', '<=', $validated['submitted_to'] . ' 23:59:59');
        }
        if (!empty($validated['updated_from'])) {
            $query->where('updated_at', '>=', $validated['updated_from'] . ' 00:00:00');
        }
        if (!empty($validated['updated_to'])) {
            $query->where('updated_at', '<=', $validated['updated_to'] . ' 23:59:59');
        }
        if (!empty($validated['account_number'])) {
            $term = '%' . addcslashes($validated['account_number'], '%_\\') . '%';
            $query->where('account_number', 'like', $term);
        }
        if (!empty($validated['company_name'])) {
            $term = '%' . addcslashes($validated['company_name'], '%_\\') . '%';
            $query->where('company_name', 'like', $term);
        }
        if (!empty($validated['product'])) {
            $term = '%' . addcslashes($validated['product'], '%_\\') . '%';
            $query->where('product', 'like', $term);
        }
        if (isset($validated['mrc']) && $validated['mrc'] !== '' && $validated['mrc'] !== null) {
            $query->where('mrc_aed', (float) $validated['mrc']);
        }
        if (isset($validated['quantity']) && $validated['quantity'] !== '' && $validated['quantity'] !== null) {
            $query->where('quantity', (int) $validated['quantity']);
        }
        if (!empty($validated['sales_agent_id'])) {
            $query->where('sales_agent_id', $validated['sales_agent_id']);
        }
        if (!empty($validated['team_leader_id'])) {
            $query->where('team_leader_id', $validated['team_leader_id']);
        }
        if (!empty($validated['manager_id'])) {
            $query->where('manager_id', $validated['manager_id']);
        }
        if (!empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($w) use ($term) {
                $w->where('company_name', 'like', $term)
                    ->orWhere('account_number', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('contact_number_gsm', 'like', $term);
            });
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        if ($sort === 'creator') {
            $query->join('users as creator_users', 'lead_submissions.created_by', '=', 'creator_users.id')
                ->orderBy('creator_users.name', $order);
        } elseif ($sort === 'type') {
            $query->leftJoin('service_types', 'lead_submissions.service_type_id', '=', 'service_types.id')
                ->orderBy('service_types.name', $order);
        } elseif ($sort === 'category') {
            $query->leftJoin('service_categories', 'lead_submissions.service_category_id', '=', 'service_categories.id')
                ->orderBy('service_categories.name', $order);
        } elseif (in_array($sort, ['sales_agent', 'team_leader', 'manager', 'executive'], true)) {
            $col = $sort === 'executive' ? 'executive_id' : $sort . '_id';
            $alias = $sort . '_users';
            $query->leftJoin("users as {$alias}", "lead_submissions.{$col}", '=', "{$alias}.id")
                ->orderBy("{$alias}.name", $order);
        } elseif ($sort === 'sla_timer') {
            $query->orderBy('lead_submissions.submitted_at', $order);
        } else {
            $query->orderBy('lead_submissions.' . $sort, $order);
        }
    }

    private function buildSelectColumns(array $columns): array
    {
        $dbColumns = array_filter($columns, fn ($c) => in_array($c, self::ALLOWED_COLUMNS, true));
        $base = array_unique(array_merge(self::BASE_COLUMNS, $dbColumns));
        if (in_array('category', $columns, true)) {
            $base[] = 'service_category_id';
        }
        if (in_array('type', $columns, true)) {
            $base[] = 'service_type_id';
        }
        if (in_array('creator', $columns, true)) {
            $base[] = 'created_by';
        }
        if (in_array('executive', $columns, true)) {
            $base[] = 'executive_id';
        }
        if (in_array('submission_type', $columns, true)) {
            $base[] = 'submission_type';
            $base[] = 'payload';
        }
        if (in_array('sla_timer', $columns, true)) {
            $base[] = 'submitted_at';
            $base[] = 'created_at';
            $base[] = 'executive_id';
        }
        foreach (['sales_agent', 'team_leader', 'manager'] as $rel) {
            if (in_array($rel, $columns, true)) {
                $base[] = $rel . '_id';
            }
        }
        $base = array_unique($base);

        return array_map(fn ($c) => 'lead_submissions.' . $c, $base);
    }

    private function formatLeadRow(LeadSubmission $lead, array $columns): array
    {
        $row = [];
        foreach ($columns as $col) {
            if ($col === 'creator') {
                $row['creator'] = $lead->relationLoaded('creator')
                    ? ['id' => $lead->creator?->id, 'name' => $lead->creator?->name ?? '-']
                    : null;
            } elseif ($col === 'type') {
                $row['type'] = $lead->relationLoaded('type') ? ($lead->type?->name ?? '-') : '-';
                $row['service_type_id'] = $lead->service_type_id;
            } elseif ($col === 'category') {
                $row['category'] = $lead->relationLoaded('category') ? ($lead->category?->name ?? '-') : '-';
                $row['service_category_id'] = $lead->service_category_id;
            } elseif ($col === 'sales_agent') {
                $row['sales_agent'] = $lead->relationLoaded('salesAgent') ? ($lead->salesAgent?->name ?? '-') : '-';
                $row['sales_agent_id'] = $lead->sales_agent_id;
            } elseif ($col === 'team_leader') {
                $row['team_leader'] = $lead->relationLoaded('teamLeader') ? ($lead->teamLeader?->name ?? '-') : '-';
                $row['team_leader_id'] = $lead->team_leader_id;
            } elseif ($col === 'manager') {
                $row['manager'] = $lead->relationLoaded('manager') ? ($lead->manager?->name ?? '-') : '-';
                $row['manager_id'] = $lead->manager_id;
            } elseif ($col === 'executive') {
                $row['executive'] = $lead->relationLoaded('executive') && $lead->executive
                    ? $lead->executive->name
                    : 'Unassigned';
                $row['executive_id'] = $lead->executive_id;
            } elseif ($col === 'submission_type') {
                $row['submission_type'] = match ($lead->submission_type) {
                    'resubmission' => 'Resubmission',
                    'new' => 'New Submission',
                    default => ! empty(($lead->payload ?? [])['is_resubmission'] ?? ($lead->payload ?? [])['resubmission_reason'] ?? null) ? 'Resubmission' : 'New Submission',
                };
            } elseif ($col === 'sla_timer') {
                $row['sla_timer'] = $this->computeLeadSlaTimer($lead);
            } elseif ($col === 'created_by') {
                $row['created_by'] = $lead->created_by;
            } elseif (in_array($col, ['created_at', 'submitted_at', 'status_changed_at', 'updated_at'], true)) {
                $row[$col] = $lead->$col ? $lead->$col->toIso8601String() : null;
            } elseif (in_array($col, ['submission_date_from', 'completion_date'], true)) {
                $row[$col] = $lead->$col ? $lead->$col->toIso8601String() : null;
            } elseif ($col === 'mrc_aed' && $lead->mrc_aed !== null) {
                $row['mrc_aed'] = number_format((float) $lead->mrc_aed, 0);
            } else {
                $row[$col] = $lead->$col ?? null;
            }
        }
        return $row;
    }

    private function computeLeadSlaTimer(LeadSubmission $lead): ?string
    {
        $startAt = $lead->submitted_at ?? $lead->created_at;
        if (! $startAt) {
            return null;
        }
        if (! empty($lead->executive_id)) {
            return 'Assigned';
        }

        $rule = SlaRule::cached()->firstWhere('module_key', 'lead_submissions');
        $fallbackMinutes = ((int) config('modules.lead_submissions.sla_days', 7)) * 24 * 60;
        $slaMinutes = ($rule && $rule->is_active)
            ? max(1, (int) $rule->sla_duration_minutes)
            : max(1, $fallbackMinutes);
        $warningMinutes = ($rule && $rule->is_active)
            ? max(0, (int) $rule->warning_threshold_minutes)
            : 0;

        $elapsed = $startAt->diffInMinutes(now());
        if ($elapsed <= $slaMinutes) {
            return $this->formatDuration($elapsed) . ' passed of ' . $this->formatDuration($slaMinutes);
        }

        $overdue = $elapsed - $slaMinutes;
        if ($warningMinutes > 0) {
            return 'Breached by ' . $this->formatDuration($overdue) . ' (' . $this->formatDuration($elapsed) . ' passed)';
        }
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

    /**
     * GET /api/lead-submissions/filters
     * Returns filter options (categories, types, statuses).
     */
    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', LeadSubmission::class);

        $userId = request()->user()->id;
        $data = \App\Services\SubmissionCacheService::rememberMeta('leads', 'filters', $userId, function () {
            $categories = ServiceCategory::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);

            $types = ServiceType::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'service_category_id']);

            $products = LeadSubmission::query()
                ->whereNotNull('product')
                ->where('product', '!=', '')
                ->distinct()
                ->pluck('product')
                ->filter()
                ->sort()
                ->values()
                ->take(50)
                ->all();

            return [
                'categories' => $categories,
                'types' => $types,
                'statuses' => [
                    ['value' => 'submitted', 'label' => 'Submitted'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                    ['value' => 'pending_for_ata', 'label' => 'Pending for ATA'],
                    ['value' => 'pending_for_finance', 'label' => 'Pending for Finance'],
                    ['value' => 'pending_from_sales', 'label' => 'pending for sales'],
                    ['value' => 'unassigned', 'label' => 'Unassigned'],
                ],
                'products' => $products,
            ];
        });

        return response()->json($data);
    }

    /**
     * GET /api/lead-submissions/columns
     * All available columns (permission-filtered) + user's visible preference.
     */
    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeadSubmission::class);

        $config = config('modules.lead_submissions.columns', []);
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

        $visible = $pref?->visible_columns ?? config('modules.lead_submissions.default_columns', []);

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    /**
     * POST /api/lead-submissions/columns
     * Save user column preferences.
     */
    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeadSubmission::class);

        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager', 'executive', 'submission_type', 'sla_timer']))],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );

        Cache::forget("col_pref_{$request->user()->id}_" . self::MODULE);

        return response()->json(['success' => true]);
    }

    /**
     * PATCH /api/lead-submissions/{lead}/status
     * Inline status edit (requires lead.edit permission).
     */
    public function updateStatus(Request $request, LeadSubmission $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(LeadSubmission::STATUSES)],
        ]);

        $lead->update([
            'status' => $data['status'],
            'status_changed_at' => now(),
        ]);

        return response()->json([
            'id' => $lead->id,
            'status' => $lead->status,
            'status_changed_at' => $lead->status_changed_at?->toIso8601String(),
        ]);
    }

    /**
     * PATCH /api/lead-submissions/{lead}/status-changed-at
     * Update last updated date/time (requires lead.edit permission).
     */
    public function updateStatusChangedAt(Request $request, LeadSubmission $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $data = $request->validate([
            'status_changed_at' => ['required', 'date'],
        ]);

        $lead->update([
            'status_changed_at' => $data['status_changed_at'],
        ]);

        return response()->json([
            'id' => $lead->id,
            'status_changed_at' => $lead->status_changed_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/lead-submissions/back-office-options
     * Options for back office edit form (executives).
     * Accessible by superadmin, back_office, or any user who can view lead submissions.
     */
    public function backOfficeOptions(Request $request): JsonResponse
    {
        $this->authorize('assignAny', LeadSubmission::class);

        $executives = Cache::remember('lead_back_office_options', 600, function () {
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

        return response()->json([
            'executives' => $executives,
            'call_verification_options' => [['value' => 'Verified', 'label' => 'Verified'], ['value' => 'Not Verified', 'label' => 'Not Verified']],
            'pending_from_sales_options' => [['value' => 'UnAssigned', 'label' => 'UnAssigned'], ['value' => 'Assigned', 'label' => 'Assigned']],
            'documents_verification_options' => [['value' => 'Verified', 'label' => 'Verified'], ['value' => 'Not Verified', 'label' => 'Not Verified']],
            'du_status_options' => [['value' => 'Submitted', 'label' => 'Submitted'], ['value' => 'In Progress', 'label' => 'In Progress'], ['value' => 'Completed', 'label' => 'Completed'], ['value' => 'Rejected', 'label' => 'Rejected']],
        ]);
    }

    /**
     * GET /api/lead-submissions/audit-log
     * List all lead submission change records (all leads). Super admin only.
     * Query: lead_submission_id (optional), page, per_page.
     */
    public function auditLog(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('superadmin')) {
            abort(403, 'Only super admin can view the audit log.');
        }

        $leadSubmissionId = $request->query('lead_submission_id');
        $perPage = min((int) $request->query('per_page', 20), 100);
        $perPage = $perPage > 0 ? $perPage : 20;

        $query = LeadSubmissionAudit::query()
            ->with(['leadSubmission:id,company_name', 'user:id,name'])
            ->orderByDesc('changed_at');

        if ($leadSubmissionId !== null && $leadSubmissionId !== '') {
            $query->where('lead_submission_id', (int) $leadSubmissionId);
        }

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function (LeadSubmissionAudit $audit) {
            return [
                'id' => $audit->id,
                'lead_submission_id' => $audit->lead_submission_id,
                'company_name' => $audit->leadSubmission?->company_name ?? '—',
                'field_name' => $audit->field_name,
                'old_value' => $audit->old_value,
                'new_value' => $audit->new_value,
                'changed_at' => $audit->changed_at?->toIso8601String(),
                'changed_by' => $audit->user?->name ?? '—',
            ];
        });

        $items = $this->resolveAuditDisplayValues($items)->values()->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/lead-submissions/{lead}/audits
     * Change history for a lead submission: field, old value, new value, date/time, who.
     */
    public function audits(Request $request, LeadSubmission $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $rows = LeadSubmissionAudit::query()
            ->where('lead_submission_id', $lead->id)
            ->with('user:id,name')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $data = $rows->map(function (LeadSubmissionAudit $audit) {
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
     * POST /api/lead-submissions/bulk-assign
     * Dispatches a queue job for instant response. Returns a tracking_id for progress polling.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $this->authorize('assignAny', LeadSubmission::class);
        $user = $request->user();

        $data = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer'],
            'executive_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ids = array_unique(array_map('intval', $data['lead_ids']));
        $executiveId = (int) $data['executive_id'];
        $executive = User::find($executiveId);

        if (! $executive || ! LeadSubmissionPolicy::isValidAssignee($executive)) {
            return response()->json([
                'message' => 'Selected assignee must be a back office user.',
            ], 422);
        }

        $existingCount = DB::table('lead_submissions')->whereIn('id', $ids)->count();
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

        BulkAssignLeadJob::dispatch($ids, $executiveId, $user->id, $trackingId);

        return response()->json([
            'message' => 'Assignment queued. Processing '.count($ids).' submission(s) in background.',
            'queued' => true,
            'count' => count($ids),
            'tracking_id' => $trackingId,
        ]);
    }

    /**
     * GET /api/lead-submissions/bulk-assign/{trackingId}/status
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
     * PUT /api/lead-submissions/{lead}/back-office
     * Edit submission (back office form). Only superadmin or backoffice role.
     */
    public function updateBackOffice(Request $request, LeadSubmission $lead): JsonResponse
    {
        $isAssignmentOnly = $request->exists('executive_id')
            && count(array_diff(array_keys($request->all()), ['executive_id', 'back_office_notes'])) === 0;

        if ($request->exists('executive_id')) {
            $this->authorize('assign', $lead);
        }
        if (! $isAssignmentOnly) {
            $this->authorize('update', $lead);
        }

        $data = $request->validate([
            // Back office fields
            'executive_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(LeadSubmission::STATUSES)],
            'call_verification' => ['nullable', 'string', 'max:50'],
            'pending_from_sales' => ['nullable', 'string', 'max:50'],
            'documents_verification' => ['nullable', 'string', 'max:50'],
            'submission_date_from' => ['nullable', 'date'],
            'back_office_notes' => ['nullable', 'string', 'max:5000'],
            'activity' => ['nullable', 'string', 'max:255'],
            'back_office_account' => ['nullable', 'string', 'max:100'],
            'work_order' => ['nullable', 'string', 'max:255'],
            'du_status' => ['nullable', 'string', 'max:50'],
            'completion_date' => ['nullable', 'date'],
            'du_remarks' => ['nullable', 'string', 'max:5000'],
            'additional_note' => ['nullable', 'string', 'max:5000'],
            // Lead fields (all optional for full edit)
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'authorized_signatory_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_number_gsm' => ['sometimes', 'nullable', 'string', 'max:50'],
            'alternate_contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'emirate' => ['sometimes', 'nullable', 'string', 'max:100'],
            'location_coordinates' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_category_id' => ['sometimes', 'nullable', 'integer', 'exists:service_categories,id'],
            'service_type_id' => ['sometimes', 'nullable', 'integer', 'exists:service_types,id'],
            'product' => ['sometimes', 'nullable', 'string', 'max:150'],
            'offer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mrc_aed' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'ae_domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gaid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'submission_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        if (array_key_exists('executive_id', $data) && ! empty($data['executive_id'])) {
            $executive = User::find((int) $data['executive_id']);
            if (! $executive || ! LeadSubmissionPolicy::isValidAssignee($executive)) {
                return response()->json([
                    'message' => 'Selected assignee must be a back office user.',
                ], 422);
            }
        }

        $lead->update($data);

        return response()->json([
            'id' => $lead->id,
            'message' => 'Submission updated.',
        ]);
    }

    /**
     * DELETE /api/lead-submissions/{lead}/documents/{document}
     * Remove a single document. Same permission as update (superadmin or back_office).
     */
    public function deleteDocument(Request $request, LeadSubmission $lead, int $document): JsonResponse
    {
        $this->authorize('update', $lead);

        $this->leadSubmissionService->deleteDocument($lead, $document);

        try {
            SystemAuditLog::record('lead_submission.document_deleted', ['document_id' => $document], null, $request->user()->id, 'lead_submission', $lead->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Document removed.']);
    }

    /**
     * POST /api/lead-submissions/{lead}/documents
     * Add documents (multipart: documents[]). Same permission as update.
     */
    public function uploadDocuments(Request $request, LeadSubmission $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['required', 'file', new AllowedDocumentFile()],
        ]);

        $this->leadSubmissionService->addDocumentsFromRequest($request, $lead);

        try {
            SystemAuditLog::record('lead_submission.documents_uploaded', null, ['count' => count($request->file('documents', []))], $request->user()->id, 'lead_submission', $lead->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Documents added.']);
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
            $teamResponse = app(\App\Http\Controllers\FieldSubmissionController::class)->teamOptions($request);
            $teamData = json_decode($teamResponse->getContent(), true) ?? [];
        } catch (\Throwable $e) {
            // silent - team options are optional for listing
        }

        // Include back office options — use same cache as backOfficeOptions()
        $boData = [];
        $user = $request->user();
        if ($user) {
            try {
                $executives = Cache::remember('lead_back_office_options', 600, function () {
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

                $boData = [
                    'executives' => $executives,
                    'call_verification_options' => [['value' => 'Verified', 'label' => 'Verified'], ['value' => 'Not Verified', 'label' => 'Not Verified']],
                    'pending_from_sales_options' => [['value' => 'UnAssigned', 'label' => 'UnAssigned'], ['value' => 'Assigned', 'label' => 'Assigned']],
                    'documents_verification_options' => [['value' => 'Verified', 'label' => 'Verified'], ['value' => 'Not Verified', 'label' => 'Not Verified']],
                    'du_status_options' => [['value' => 'Submitted', 'label' => 'Submitted'], ['value' => 'In Progress', 'label' => 'In Progress'], ['value' => 'Completed', 'label' => 'Completed'], ['value' => 'Rejected', 'label' => 'Rejected']],
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
            'back_office_options' => $boData,
        ]);
    }
}
