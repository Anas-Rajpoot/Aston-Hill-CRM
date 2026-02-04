<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadSubmission;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Optimized Lead Submissions Listing API.
 * Target: < 1s response time. Uses scopes, eager loading, column selection, cached preferences.
 */
class LeadSubmissionApiController extends Controller
{
    private const MODULE = 'lead_submissions';

    /** Whitelist of allowed columns for SELECT (prevents SQL injection / unauthorized exposure). */
    private const ALLOWED_COLUMNS = [
        'id', 'company_name', 'account_number', 'email', 'contact_number_gsm',
        'service_category_id', 'service_type_id', 'status', 'status_changed_at',
        'created_at', 'submitted_at', 'created_by', 'product', 'mrc_aed', 'quantity',
    ];

    /** Base columns required for every row (id for links, status for inline edit). */
    private const BASE_COLUMNS = ['id', 'status'];

    public function __construct()
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
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager']))],
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
            'mrc_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'mrc_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'quantity_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'quantity_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';

        // Base query: visibility + filters only (no joins). Used for fast count.
        $baseQuery = LeadSubmission::query()->visibleTo($user);
        $this->applyFilters($baseQuery, $validated);

        $total = $baseQuery->count();

        // Data query: add sort (with joins), select, eager load, then skip/take.
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
        if (!empty($eagerLoad)) {
            $dataQuery->with($eagerLoad);
        }

        $offset = ($page - 1) * $perPage;
        $leads = $dataQuery->skip($offset)->take($perPage)->get();

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
            $allowed = array_intersect($requestColumns, array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager']));
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

        $allowed = array_intersect($cols, array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager']));
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function applyFilters($query, array $validated): void
    {
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
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
        if (isset($validated['mrc_min']) && $validated['mrc_min'] !== '' && $validated['mrc_min'] !== null) {
            $query->where('mrc_aed', '>=', (float) $validated['mrc_min']);
        }
        if (isset($validated['mrc_max']) && $validated['mrc_max'] !== '' && $validated['mrc_max'] !== null) {
            $query->where('mrc_aed', '<=', (float) $validated['mrc_max']);
        }
        if (isset($validated['quantity_min']) && $validated['quantity_min'] !== '' && $validated['quantity_min'] !== null) {
            $query->where('quantity', '>=', (int) $validated['quantity_min']);
        }
        if (isset($validated['quantity_max']) && $validated['quantity_max'] !== '' && $validated['quantity_max'] !== null) {
            $query->where('quantity', '<=', (int) $validated['quantity_max']);
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
        } elseif (in_array($sort, ['sales_agent', 'team_leader', 'manager'], true)) {
            $col = $sort . '_id';
            $alias = $sort . '_users';
            $query->leftJoin("users as {$alias}", "lead_submissions.{$col}", '=', "{$alias}.id")
                ->orderBy("{$alias}.name", $order);
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
            } elseif ($col === 'category') {
                $row['category'] = $lead->relationLoaded('category') ? ($lead->category?->name ?? '-') : '-';
            } elseif ($col === 'sales_agent') {
                $row['sales_agent'] = $lead->relationLoaded('salesAgent') ? ($lead->salesAgent?->name ?? '-') : '-';
            } elseif ($col === 'team_leader') {
                $row['team_leader'] = $lead->relationLoaded('teamLeader') ? ($lead->teamLeader?->name ?? '-') : '-';
            } elseif ($col === 'manager') {
                $row['manager'] = $lead->relationLoaded('manager') ? ($lead->manager?->name ?? '-') : '-';
            } elseif (in_array($col, ['created_at', 'submitted_at', 'status_changed_at'], true)) {
                $row[$col] = $lead->$col ? $lead->$col->toIso8601String() : null;
            } elseif ($col === 'mrc_aed' && $lead->mrc_aed !== null) {
                $row['mrc_aed'] = number_format((float) $lead->mrc_aed, 0);
            } else {
                $row[$col] = $lead->$col ?? null;
            }
        }
        return $row;
    }

    /**
     * GET /api/lead-submissions/filters
     * Returns filter options (categories, types, statuses).
     */
    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', LeadSubmission::class);

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

        return response()->json([
            'categories' => $categories,
            'types' => $types,
            'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)], LeadSubmission::STATUSES),
            'products' => $products,
        ]);
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
            'visible_columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['creator', 'type', 'category', 'sales_agent', 'team_leader', 'manager']))],
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
     * Options for back office edit form (executives). Only superadmin or backoffice.
     */
    public function backOfficeOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('superadmin') && ! $user->hasRole('back_office')) {
            abort(403, 'Unauthorized.');
        }

        $executives = User::role('back_office')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);

        if ($executives->isEmpty()) {
            $executives = User::orderBy('name')->take(50)->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);
        }

        return response()->json([
            'executives' => $executives->values()->all(),
            'call_verification_options' => [['value' => 'Verified', 'label' => 'Verified'], ['value' => 'Not Verified', 'label' => 'Not Verified']],
            'pending_from_sales_options' => [['value' => 'UnAssigned', 'label' => 'UnAssigned'], ['value' => 'Assigned', 'label' => 'Assigned']],
            'documents_verification_options' => [['value' => 'Verified', 'label' => 'Verified'], ['value' => 'Not Verified', 'label' => 'Not Verified']],
            'du_status_options' => [['value' => 'Submitted', 'label' => 'Submitted'], ['value' => 'In Progress', 'label' => 'In Progress'], ['value' => 'Completed', 'label' => 'Completed']],
        ]);
    }

    /**
     * PUT /api/lead-submissions/{lead}/back-office
     * Edit submission (back office form). Only superadmin or backoffice role.
     */
    public function updateBackOffice(Request $request, LeadSubmission $lead): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('superadmin') && ! $user->hasRole('back_office')) {
            abort(403, 'Only superadmin or back office can edit this submission.');
        }

        $data = $request->validate([
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
        ]);

        $lead->update($data);

        return response()->json([
            'id' => $lead->id,
            'message' => 'Submission updated.',
        ]);
    }
}
