<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FieldSubmissionController;
use App\Models\FieldSubmission;
use App\Models\FieldSubmissionAudit;
use App\Models\SlaRule;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserColumnPreference;
use App\Policies\FieldSubmissionPolicy;
use App\Traits\StoresFieldSubmissionDocuments;
use App\Models\FieldSubmissionDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Field Submissions Listing API – same pattern as Lead Submissions.
 */
class FieldSubmissionApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;
    use StoresFieldSubmissionDocuments;

    /** @var array<int, string>|null */
    private ?array $tableColumns = null;

    private const MODULE = 'field_submissions';

    private const MAX_DOCUMENT_SIZE_MB = 10;

    private const MAX_DOCUMENTS_TOTAL_MB = 20;

    private const ALLOWED_COLUMNS = [
        'id', 'account_number', 'company_name', 'authorized_signatory_name', 'contact_number', 'product', 'alternate_number', 'emirates', 'location_coordinates', 'complete_address',
        'additional_notes', 'special_instruction',
        'status', 'created_at', 'created_by',
        'manager_id', 'team_leader_id', 'sales_agent_id',
        'field_executive_id', 'field_status', 'meeting_date', 'remarks_by_field_agent', 'updated_at',
    ];

    /** Columns that are computed or from relations in formatRow (no direct DB select). */
    private const COMPUTED_COLUMNS = ['field_agent', 'target_date', 'sla_timer', 'sla_status', 'last_updated'];

    private const BASE_COLUMNS = ['id', 'status'];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FieldSubmission::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, self::COMPUTED_COLUMNS, ['creator', 'sales_agent', 'team_leader', 'manager', 'field_agent']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, self::COMPUTED_COLUMNS, ['creator', 'sales_agent', 'team_leader', 'manager', 'field_agent']))],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(FieldSubmission::STATUSES)],
            'field_status' => ['sometimes', 'nullable', 'string', 'max:150'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'product' => ['sometimes', 'nullable', 'string', 'max:150'],
            'emirates' => ['sometimes', 'nullable', 'string', 'max:100'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'field_executive_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';

        // Single data query with sort, select, eager load, then paginate.
        $dataQuery = FieldSubmission::query()->visibleTo($user);
        $this->applyFilters($dataQuery, $validated);
        $this->applySort($dataQuery, $sort, $order);

        $selectColumns = $this->buildSelectColumns($columns);
        $dataQuery->select($selectColumns);

        $eagerLoad = [];
        if (in_array('creator', $columns, true)) {
            $eagerLoad['creator'] = fn ($q) => $q->select('id', 'name', 'email');
        }
        if (in_array('sales_agent', $columns, true) || in_array('team_leader', $columns, true) || in_array('manager', $columns, true)) {
            $eagerLoad['salesAgent'] = fn ($q) => $q->select('id', 'name');
            $eagerLoad['teamLeader'] = fn ($q) => $q->select('id', 'name');
            $eagerLoad['manager'] = fn ($q) => $q->select('id', 'name');
        }
        if (in_array('field_agent', $columns, true)) {
            $eagerLoad['fieldExecutive'] = fn ($q) => $q->select('id', 'name');
        }
        if (!empty($eagerLoad)) {
            $dataQuery->with($eagerLoad);
        }

        $offset = ($page - 1) * $perPage;
        $items = $dataQuery->skip($offset)->take($perPage)->get()->map(function ($row) use ($columns) {
            return $this->formatRow($row, $columns);
        });

        // Cache the count for 30s to avoid expensive COUNT(*) on every paginate/filter.
        $countCacheKey = 'field_count_' . $user->id . '_' . md5(json_encode($validated));
        $total = Cache::remember($countCacheKey, 30, function () use ($user, $validated) {
            $cq = FieldSubmission::query()->visibleTo($user);
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

    private function resolveColumns($user, ?array $requestColumns): array
    {
        $allAllowed = array_merge(self::ALLOWED_COLUMNS, self::COMPUTED_COLUMNS, ['creator', 'sales_agent', 'team_leader', 'manager', 'field_agent']);
        if (!empty($requestColumns)) {
            $allowed = array_intersect($requestColumns, $allAllowed);
            return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
        }

        $cacheKey = "col_pref_{$user->id}_" . self::MODULE;
        $preference = Cache::remember($cacheKey, 3600, function () use ($user) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', self::MODULE)
                ->first();
        });

        $cols = $preference?->visible_columns ?? config('modules.field_submissions.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        $allowed = array_intersect($cols, $allAllowed);
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function applyFilters($query, array $validated): void
    {
        if (!empty($validated['status'])) {
            if ($validated['status'] === 'unassigned') {
                $query->whereNull('field_executive_id');
            } else {
                $query->where('status', $validated['status']);
            }
        }
        if (!empty($validated['field_status'])) {
            if ($validated['field_status'] === 'unassigned') {
                $query->where(function ($w) {
                    $w->whereNull('field_status')->orWhere('field_status', '');
                });
            } else {
                $query->where('field_status', $validated['field_status']);
            }
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
        if (!empty($validated['company_name'])) {
            $term = '%' . addcslashes($validated['company_name'], '%_\\') . '%';
            $query->where('company_name', 'like', $term);
        }
        if (!empty($validated['product'])) {
            $term = '%' . addcslashes($validated['product'], '%_\\') . '%';
            $query->where('product', 'like', $term);
        }
        if (!empty($validated['emirates'])) {
            $term = '%' . addcslashes($validated['emirates'], '%_\\') . '%';
            $query->where('emirates', 'like', $term);
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
        if (!empty($validated['field_executive_id'])) {
            $query->where('field_executive_id', $validated['field_executive_id']);
        }
        if (!empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($w) use ($term) {
                $w->where('company_name', 'like', $term)
                    ->orWhere('contact_number', 'like', $term)
                    ->orWhere('product', 'like', $term)
                    ->orWhere('emirates', 'like', $term);
            });
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        $existing = $this->getExistingTableColumns();

        if ($sort === 'creator') {
            $query->join('users as creator_users', 'field_submissions.created_by', '=', 'creator_users.id')
                ->orderBy('creator_users.name', $order);
        } elseif (in_array($sort, ['sales_agent', 'team_leader', 'manager'], true)) {
            $col = $sort . '_id';
            $alias = $sort . '_users';
            $query->leftJoin("users as {$alias}", "field_submissions.{$col}", '=', "{$alias}.id")
                ->orderBy("{$alias}.name", $order);
        } elseif ($sort === 'field_agent') {
            $query->leftJoin('users as field_agent_users', 'field_submissions.field_executive_id', '=', 'field_agent_users.id')
                ->orderBy('field_agent_users.name', $order);
        } elseif (in_array($sort, ['target_date', 'sla_timer', 'sla_status', 'last_updated'], true)) {
            if ($sort === 'sla_timer' || $sort === 'sla_status') {
                if (in_array('submitted_at', $existing, true)) {
                    $query->orderByRaw("COALESCE(field_submissions.submitted_at, field_submissions.created_at) {$order}");
                } else {
                    $query->orderBy('field_submissions.created_at', $order);
                }
            } else {
                $dbCol = $sort === 'target_date' ? 'meeting_date' : 'updated_at';
                if (in_array($dbCol, $existing, true)) {
                    $query->orderBy('field_submissions.' . $dbCol, $order);
                } else {
                    $query->orderBy('field_submissions.created_at', $order);
                }
            }
        } elseif (in_array($sort, self::ALLOWED_COLUMNS, true)) {
            if (in_array($sort, $existing, true)) {
                $query->orderBy('field_submissions.' . $sort, $order);
            } else {
                $fallback = in_array('submitted_at', $existing, true) ? 'submitted_at' : 'created_at';
                $query->orderBy('field_submissions.' . $fallback, $order);
            }
        }
    }

    private function buildSelectColumns(array $columns): array
    {
        $dbColumns = array_filter($columns, fn ($c) => in_array($c, self::ALLOWED_COLUMNS, true));
        $base = array_unique(array_merge(self::BASE_COLUMNS, $dbColumns));
        if (in_array('creator', $columns, true)) {
            $base[] = 'created_by';
        }
        foreach (['sales_agent', 'team_leader', 'manager', 'field_agent'] as $rel) {
            $idCol = $rel === 'field_agent' ? 'field_executive_id' : $rel . '_id';
            if (in_array($rel, $columns, true) && in_array($idCol, $base, true) === false) {
                $base[] = $idCol;
            }
        }
        if (in_array('status', $columns, true) && in_array('field_executive_id', $base, true) === false) {
            $base[] = 'field_executive_id';
        }
        if (in_array('field_status', $columns, true) || in_array('target_date', $columns, true) || in_array('sla_timer', $columns, true) || in_array('sla_status', $columns, true)) {
            $base[] = 'meeting_date';
            $base[] = 'field_status';
            $base[] = 'created_at';
            $base[] = 'submitted_at';
            $base[] = 'field_executive_id';
        }
        if (in_array('last_updated', $columns, true)) {
            $base[] = 'updated_at';
        }
        $base = array_unique($base);
        $existing = $this->getExistingTableColumns();
        $base = array_values(array_filter($base, fn ($c) => in_array($c, $existing, true)));

        return array_map(fn ($c) => 'field_submissions.' . $c, $base);
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
            $this->tableColumns = Schema::getColumnListing('field_submissions');
        } catch (\Throwable $e) {
            $this->tableColumns = self::ALLOWED_COLUMNS;
        }

        return $this->tableColumns;
    }

    private function formatRow(FieldSubmission $row, array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            if ($col === 'creator') {
                $out['creator'] = $row->relationLoaded('creator')
                    ? ['id' => $row->creator?->id, 'name' => $row->creator?->name ?? '-']
                    : null;
            } elseif ($col === 'sales_agent') {
                $out['sales_agent'] = $row->relationLoaded('salesAgent') ? ($row->salesAgent?->name ?? '-') : '-';
                $out['sales_agent_id'] = $row->sales_agent_id;
            } elseif ($col === 'team_leader') {
                $out['team_leader'] = $row->relationLoaded('teamLeader') ? ($row->teamLeader?->name ?? '-') : '-';
                $out['team_leader_id'] = $row->team_leader_id;
            } elseif ($col === 'manager') {
                $out['manager'] = $row->relationLoaded('manager') ? ($row->manager?->name ?? '-') : '-';
                $out['manager_id'] = $row->manager_id;
            } elseif ($col === 'field_agent') {
                $name = $row->relationLoaded('fieldExecutive') ? ($row->fieldExecutive?->name ?? null) : null;
                $out['field_agent'] = $name ?: 'Unassigned';
                $out['field_executive_id'] = $row->field_executive_id;
            } elseif ($col === 'target_date') {
                $out['target_date'] = $row->meeting_date ? $row->meeting_date->format('d-M-Y H:i:s') : null;
                $out['meeting_date'] = $row->meeting_date ? $row->meeting_date->toIso8601String() : null;
            } elseif ($col === 'last_updated') {
                    $out['last_updated'] = $row->updated_at ? $row->updated_at->format('d-M-Y H:i:s') : null;
            } elseif ($col === 'sla_timer') {
                $out['sla_timer'] = $this->computeSlaTimer($row);
            } elseif ($col === 'sla_status') {
                $out['sla_status'] = $this->computeSlaStatus($row);
            } elseif (in_array($col, ['submitted_at'], true)) {
                    $out[$col] = $row->$col ? $row->$col->format('d-M-Y H:i:s') : null;
            } elseif (in_array($col, ['created_at'], true)) {
                $out[$col] = $row->$col ? $row->$col->toIso8601String() : null;
            } elseif ($col === 'status') {
                $out['status'] = empty($row->field_executive_id) ? 'unassigned' : ($row->status ?? null);
            } else {
                $out[$col] = $row->$col ?? null;
            }
        }
        return $out;
    }

    private function computeSlaTimer(FieldSubmission $row): ?string
    {
        $startAt = $row->submitted_at ?? $row->created_at;
        if (! $startAt) {
            return null;
        }
        if (! empty($row->field_executive_id)) {
            return 'Assigned';
        }

        $rule = SlaRule::cached()->firstWhere('module_key', 'field_submissions');
        $slaMinutes = ($rule && $rule->is_active)
            ? max(1, (int) $rule->sla_duration_minutes)
            : 240;

        $elapsed = $startAt->diffInMinutes(now());
        if ($elapsed <= $slaMinutes) {
            return $this->formatDuration($elapsed) . ' passed of ' . $this->formatDuration($slaMinutes);
        }

        $overdue = $elapsed - $slaMinutes;
        return 'Breached by ' . $this->formatDuration($overdue);
    }

    private function computeSlaStatus(FieldSubmission $row): ?string
    {
        $startAt = $row->submitted_at ?? $row->created_at;
        if (! $startAt) {
            return null;
        }
        if (! empty($row->field_executive_id)) {
            return 'Assigned';
        }

        $rule = SlaRule::cached()->firstWhere('module_key', 'field_submissions');
        $slaMinutes = ($rule && $rule->is_active)
            ? max(1, (int) $rule->sla_duration_minutes)
            : 240;
        $warningMinutes = ($rule && $rule->is_active)
            ? max(0, (int) $rule->warning_threshold_minutes)
            : 30;

        $elapsed = $startAt->diffInMinutes(now());
        $remaining = $slaMinutes - $elapsed;
        if ($remaining <= 0) {
            return 'Breached';
        }
        return $remaining <= $warningMinutes ? 'Approaching' : 'On Time';
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
        $this->authorize('viewAny', FieldSubmission::class);

        $userId = request()->user()->id;
        $data = \App\Services\SubmissionCacheService::rememberMeta('field', 'filters', $userId, function () {
            $products = FieldSubmission::query()
                ->whereNotNull('product')
                ->where('product', '!=', '')
                ->distinct()
                ->pluck('product')
                ->filter()
                ->sort()
                ->values()
                ->take(50)
                ->all();

            $emirates = FieldSubmission::query()
                ->whereNotNull('emirates')
                ->where('emirates', '!=', '')
                ->distinct()
                ->pluck('emirates')
                ->filter()
                ->sort()
                ->values()
                ->take(30)
                ->all();

            return [
                'statuses' => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'submitted', 'label' => 'Submitted'],
                    ['value' => 'unassigned', 'label' => 'UnAssigned'],
                ],
                'field_statuses' => array_map(fn ($status) => ['value' => $status, 'label' => $status], FieldSubmission::FIELD_STATUSES),
                'products' => array_values($products),
                'emirates' => array_values($emirates),
            ];
        });

        return response()->json($data);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FieldSubmission::class);

        $config = config('modules.field_submissions.columns', []);
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

        $visible = $pref?->visible_columns ?? config('modules.field_submissions.default_columns', []);
        $validKeys = array_keys($config);
        $visible = array_values(array_intersect($visible, $validKeys));

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FieldSubmission::class);

        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, self::COMPUTED_COLUMNS, ['creator', 'sales_agent', 'team_leader', 'manager', 'field_agent', 'field_status']))],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );

        Cache::forget("col_pref_{$request->user()->id}_" . self::MODULE);

        return response()->json(['success' => true]);
    }

    public function updateStatus(Request $request, FieldSubmission $fieldSubmission): JsonResponse
    {
        $this->authorize('update', $fieldSubmission);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(FieldSubmission::STATUSES)],
        ]);

        $fieldSubmission->update([
            'status' => $data['status'],
            'submitted_at' => $data['status'] === 'submitted' ? now() : $fieldSubmission->submitted_at,
        ]);

        return response()->json([
            'id' => $fieldSubmission->id,
            'status' => $fieldSubmission->status,
            'submitted_at' => $fieldSubmission->submitted_at?->format('d-M-Y'),
        ]);
    }

    /**
     * GET /api/field-submissions/{fieldSubmission}
     * Single field submission for view/edit (authorized by field_head.view).
     */
    public function show(Request $request, FieldSubmission $fieldSubmission): JsonResponse
    {
        $this->authorize('view', $fieldSubmission);

        $fieldSubmission->load([
            'creator:id,name',
            'manager:id,name',
            'teamLeader:id,name',
            'salesAgent:id,name',
            'fieldExecutive:id,name',
            'documents',
        ]);

        $documents = $fieldSubmission->documents->map(fn ($d) => [
            'id' => $d->id,
            'doc_key' => $d->doc_key,
            'label' => $d->label ?? $d->doc_key,
            'file_path' => $d->file_path,
            'original_name' => $d->file_name,
            'mime' => $d->mime,
            'size' => $d->size,
        ])->values()->all();

        return response()->json([
            'id' => $fieldSubmission->id,
            'account_number' => $fieldSubmission->account_number,
            'company_name' => $fieldSubmission->company_name,
            'authorized_signatory_name' => $fieldSubmission->authorized_signatory_name,
            'contact_number' => $fieldSubmission->contact_number,
            'product' => $fieldSubmission->product,
            'alternate_number' => $fieldSubmission->alternate_number,
            'emirates' => $fieldSubmission->emirates,
            'location_coordinates' => $fieldSubmission->location_coordinates,
            'complete_address' => $fieldSubmission->complete_address,
            'additional_notes' => $fieldSubmission->additional_notes,
            'special_instruction' => $fieldSubmission->special_instruction,
            'manager_id' => $fieldSubmission->manager_id,
            'team_leader_id' => $fieldSubmission->team_leader_id,
            'sales_agent_id' => $fieldSubmission->sales_agent_id,
            'field_executive_id' => $fieldSubmission->field_executive_id,
            'meeting_date' => $fieldSubmission->meeting_date?->format('Y-m-d\\TH:i:s'),
            'field_status' => $fieldSubmission->field_status,
            'remarks_by_field_agent' => $fieldSubmission->remarks_by_field_agent,
            'status' => $fieldSubmission->status,
            'submitted_at' => $fieldSubmission->submitted_at?->toIso8601String(),
            'created_at' => $fieldSubmission->created_at?->toIso8601String(),
            'updated_at' => $fieldSubmission->updated_at?->toIso8601String(),
            'manager_name' => $fieldSubmission->manager?->name,
            'team_leader_name' => $fieldSubmission->teamLeader?->name,
            'sales_agent_name' => $fieldSubmission->salesAgent?->name,
            'field_executive_name' => $fieldSubmission->fieldExecutive?->name,
            'creator_name' => $fieldSubmission->creator?->name,
            'documents' => $documents,
        ]);
    }

    public function destroy(Request $request, FieldSubmission $fieldSubmission): JsonResponse
    {
        $this->authorize('delete', $fieldSubmission);

        $submissionId = (int) $fieldSubmission->id;
        $fieldSubmission->delete();

        Storage::disk('public')->deleteDirectory("field-submissions/{$submissionId}");

        SystemAuditLog::record('field_submission.deleted', [
            'field_submission_id' => $submissionId,
        ], null, $request->user()->id, 'field_submission', $submissionId);

        return response()->json(['message' => 'Field submission deleted.']);
    }

    /**
     * PUT /api/field-submissions/{fieldSubmission}
     * Update field submission (Field Head Edit). Authorized by field_head.view (update policy).
     * Accepts JSON or multipart; optional documents[] for photographic proof.
     */
    public function update(Request $request, FieldSubmission $fieldSubmission): JsonResponse
    {
        $isAssignmentOnly = $request->exists('field_executive_id')
            && count(array_diff(array_keys($request->all()), ['field_executive_id'])) === 0;

        if ($request->exists('field_executive_id')) {
            $this->authorize('assign', $fieldSubmission);
        }
        if (! $isAssignmentOnly) {
            $this->authorize('update', $fieldSubmission);
        }

        $rules = [
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:50'],
            'complete_address' => ['sometimes', 'required', 'string', 'max:1000'],
            'product' => ['sometimes', 'required', 'string', 'max:255'],
            'emirates' => ['sometimes', 'required', 'string', 'max:100'],
            'location_coordinates' => ['nullable', 'string', 'max:100'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'special_instruction' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['sometimes', 'required', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'field_executive_id' => ['nullable', 'exists:users,id'],
            'meeting_date' => ['nullable', 'date'],
            'field_status' => ['nullable', 'string', 'max:80', Rule::in(FieldSubmission::FIELD_STATUSES)],
            'remarks_by_field_agent' => ['nullable', 'string', 'max:5000'],
            'documents' => ['sometimes', 'array', 'max:10'],
            'documents.*' => ['file', 'max:' . (self::MAX_DOCUMENT_SIZE_MB * 1024), 'mimes:pdf,doc,docx,jpg,jpeg,png,eml'],
        ];

        $data = $request->validate($rules);

        if (array_key_exists('field_executive_id', $data) && ! empty($data['field_executive_id'])) {
            $assignee = User::find((int) $data['field_executive_id']);
            if (! $assignee || ! FieldSubmissionPolicy::isValidAssignee($assignee)) {
                return response()->json([
                    'message' => 'Selected assignee must be a field agent or field operations head.',
                ], 422);
            }
        }

        $documents = $request->file('documents', []);
        $documents = is_array($documents) ? $documents : [];

        $existingSize = $fieldSubmission->documents()->sum('size');
        $newSize = 0;
        foreach ($documents as $file) {
            if ($file && $file->isValid()) {
                $newSize += $file->getSize();
            }
        }
        if (($existingSize + $newSize) > (self::MAX_DOCUMENTS_TOTAL_MB * 1024 * 1024)) {
            return response()->json([
                'message' => 'Total document size must not exceed ' . self::MAX_DOCUMENTS_TOTAL_MB . 'MB.',
                'errors' => ['documents' => ['Total size limit exceeded.']],
            ], 422);
        }

        $toUpdate = array_diff_key($data, array_flip(['documents']));
        $fieldSubmission->update($toUpdate);

        foreach ($documents as $file) {
            if ($file && $file->isValid()) {
                $this->storeFieldSubmissionDocument($fieldSubmission, 'photographic_proof', $file, 'Photographic Proof');
            }
        }

        return response()->json([
            'id' => $fieldSubmission->id,
            'message' => 'Field submission updated.',
        ]);
    }

    /**
     * PATCH /api/field-submissions/{fieldSubmission}
     * Partial update for listing inline edits. Only provided fields are validated and updated.
     * Cascade: when manager_id is set, clear team_leader_id/sales_agent_id if they don't belong to that manager.
     * When sales_agent_id is set, auto-set team_leader_id and manager_id from that user's hierarchy.
     * When team_leader_id is set, auto-set manager_id and clear sales_agent_id if it doesn't belong to that TL.
     */
    public function patch(Request $request, FieldSubmission $fieldSubmission): JsonResponse
    {
        $isAssignmentOnly = $request->exists('field_executive_id')
            && count(array_diff(array_keys($request->all()), ['field_executive_id'])) === 0;

        if ($request->exists('field_executive_id')) {
            $this->authorize('assign', $fieldSubmission);
        }
        if (! $isAssignmentOnly) {
            $this->authorize('update', $fieldSubmission);
        }

        $rules = [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'contact_number' => ['sometimes', 'string', 'max:50'],
            'complete_address' => ['sometimes', 'string', 'max:1000'],
            'product' => ['sometimes', 'string', 'max:255'],
            'emirates' => ['sometimes', 'string', 'max:100'],
            'manager_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'field_executive_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'meeting_date' => ['sometimes', 'nullable', 'date'],
            'field_status' => ['sometimes', 'nullable', 'string', 'max:80', Rule::in(FieldSubmission::FIELD_STATUSES)],
        ];

        $data = $request->validate($rules);
        if (array_key_exists('field_executive_id', $data) && ! empty($data['field_executive_id'])) {
            $assignee = User::find((int) $data['field_executive_id']);
            if (! $assignee || ! FieldSubmissionPolicy::isValidAssignee($assignee)) {
                return response()->json([
                    'message' => 'Selected assignee must be a field agent or field operations head.',
                ], 422);
            }
        }
        if (! empty($data)) {
            $this->applyHierarchyCascade($fieldSubmission, $data);
            $fieldSubmission->update($data);
        }

        $fieldSubmission->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'fieldExecutive:id,name', 'creator:id,name']);
        $columns = array_merge(
            ['id', 'company_name', 'contact_number', 'product', 'emirates', 'complete_address', 'status', 'field_status', 'manager', 'team_leader', 'sales_agent', 'field_agent', 'target_date', 'last_updated', 'creator'],
            array_keys($data)
        );
        $columns = array_unique($columns);
        $row = $this->formatRow($fieldSubmission, $columns);

        return response()->json([
            'id' => $fieldSubmission->id,
            'message' => 'Updated.',
            'row' => $row,
        ]);
    }

    /**
     * Apply hierarchy cascade to $data: when manager/team_leader/sales_agent is updated,
     * set or clear related fields so they stay consistent.
     */
    private function applyHierarchyCascade(FieldSubmission $fieldSubmission, array &$data): void
    {
        // When sales_agent_id is set: auto-set team_leader_id and manager_id from that user.
        if (array_key_exists('sales_agent_id', $data) && $data['sales_agent_id']) {
            $user = User::with('teamLeader:id,manager_id')->find($data['sales_agent_id']);
            if ($user) {
                $data['team_leader_id'] = $user->team_leader_id;
                $data['manager_id'] = $user->teamLeader?->manager_id ?? $user->manager_id;
            }
        }

        // When team_leader_id is set: auto-set manager_id; clear sales_agent_id if it doesn't belong to this TL.
        if (array_key_exists('team_leader_id', $data)) {
            $newTlId = $data['team_leader_id'];
            if ($newTlId) {
                $tlUser = User::find($newTlId);
                if ($tlUser) {
                    $data['manager_id'] = $tlUser->manager_id;
                }
            }
            $currentSaId = $fieldSubmission->sales_agent_id ?? $data['sales_agent_id'] ?? null;
            if ($currentSaId) {
                $saUser = User::find($currentSaId);
                if ($saUser && (int) $saUser->team_leader_id !== (int) $newTlId) {
                    $data['sales_agent_id'] = null;
                }
            }
        }

        // When manager_id is set: clear team_leader_id and sales_agent_id if they don't belong to this manager.
        if (array_key_exists('manager_id', $data)) {
            $newManagerId = $data['manager_id'];
            if ($newManagerId) {
                $currentTlId = $fieldSubmission->team_leader_id ?? $data['team_leader_id'] ?? null;
                if ($currentTlId) {
                    $tlUser = User::find($currentTlId);
                    if ($tlUser && (int) $tlUser->manager_id !== (int) $newManagerId) {
                        $data['team_leader_id'] = null;
                        $data['sales_agent_id'] = null;
                    }
                }
                $currentSaId = $fieldSubmission->sales_agent_id ?? $data['sales_agent_id'] ?? null;
                if ($currentSaId && ! isset($data['team_leader_id'])) {
                    $saUser = User::with('teamLeader:id,manager_id')->find($currentSaId);
                    $effectiveManager = $saUser?->teamLeader?->manager_id ?? $saUser?->manager_id;
                    if ($effectiveManager && (int) $effectiveManager !== (int) $newManagerId) {
                        $data['sales_agent_id'] = null;
                    }
                }
            } else {
                $data['team_leader_id'] = null;
                $data['sales_agent_id'] = null;
            }
        }
    }

    /**
     * PATCH /api/field-submissions/{fieldSubmission}/assign-field-technician
     * Assign a field technician (field executive) to a submission. Body: field_executive_id.
     */
    public function assignFieldTechnician(Request $request, FieldSubmission $fieldSubmission): JsonResponse
    {
        $this->authorize('assign', $fieldSubmission);

        $data = $request->validate([
            'field_executive_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignee = User::find((int) $data['field_executive_id']);
        if (! $assignee || ! FieldSubmissionPolicy::isValidAssignee($assignee)) {
            return response()->json([
                'message' => 'Selected assignee must be a field agent or field operations head.',
            ], 422);
        }

        $fieldSubmission->update(['field_executive_id' => $data['field_executive_id']]);

        return response()->json([
            'id' => $fieldSubmission->id,
            'message' => 'Field technician assigned.',
        ]);
    }

    /**
     * GET /api/field-submissions/{fieldSubmission}/documents/{document}/download
     * Download a single document. Authorized same as view.
     */
    public function downloadDocument(Request $request, FieldSubmission $fieldSubmission, int $document)
    {
        $this->authorize('view', $fieldSubmission);

        $doc = FieldSubmissionDocument::where('field_submission_id', $fieldSubmission->id)
            ->where('id', $document)
            ->first();
        if (! $doc || ! $doc->file_path) {
            return response()->json(['message' => 'Document not found.'], 404);
        }
        $fullPath = Storage::disk('public')->path($doc->file_path);
        if (! is_file($fullPath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        $filename = $doc->file_name ?: basename($doc->file_path);

        return response()->file($fullPath, [
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * GET /api/field-submissions/edit-options
     * Options for field head edit form (emirates, field statuses). Requires field_head.view.
     */
    public function editOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FieldSubmission::class);

        $emirates = FieldSubmission::query()
            ->whereNotNull('emirates')
            ->where('emirates', '!=', '')
            ->distinct()
            ->pluck('emirates')
            ->filter()
            ->sort()
            ->values()
            ->take(30)
            ->all();

        return response()->json([
            'emirates' => array_values($emirates),
            'field_statuses' => array_map(fn ($s) => ['value' => $s, 'label' => $s], FieldSubmission::FIELD_STATUSES),
        ]);
    }

    /**
     * GET /api/field-submissions/audit-log
     * List all field submission change records. Super admin only.
     * Query: field_submission_id (optional), page, per_page.
     */
    public function auditLog(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('superadmin')) {
            abort(403, 'Only super admin can view the audit log.');
        }

        $fieldSubmissionId = $request->query('field_submission_id');
        $perPage = min((int) $request->query('per_page', 20), 100);
        $perPage = $perPage > 0 ? $perPage : 20;

        $query = FieldSubmissionAudit::query()
            ->with(['fieldSubmission:id,company_name', 'changedByUser:id,name'])
            ->orderByDesc('changed_at');

        if ($fieldSubmissionId !== null && $fieldSubmissionId !== '') {
            $query->where('field_submission_id', (int) $fieldSubmissionId);
        }

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function (FieldSubmissionAudit $audit) {
            return [
                'id' => $audit->id,
                'field_submission_id' => $audit->field_submission_id,
                'company_name' => $audit->fieldSubmission?->company_name ?? '—',
                'field_name' => $audit->field_name,
                'old_value' => $audit->old_value,
                'new_value' => $audit->new_value,
                'changed_at' => $audit->changed_at?->toIso8601String(),
                'changed_by' => $audit->changedByUser?->name ?? '—',
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
     * GET /api/field-submissions/{fieldSubmission}/audits
     * Change history for a field submission: field, old value, new value, date/time, who.
     */
    public function audits(Request $request, FieldSubmission $fieldSubmission): JsonResponse
    {
        $this->authorize('view', $fieldSubmission);

        $rows = FieldSubmissionAudit::query()
            ->where('field_submission_id', $fieldSubmission->id)
            ->with('changedByUser:id,name')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $data = $rows->map(function (FieldSubmissionAudit $audit) {
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
     * GET /api/field-submissions/field-agent-options
     * Returns list of field agents for the assign modal.
     * Accessible by superadmin, field roles, or any user who can view field submissions.
     */
    public function fieldAgentOptions(Request $request): JsonResponse
    {
        $this->authorize('assignAny', FieldSubmission::class);

        $agents = Cache::remember('field_agent_options', 600, function () {
            return DB::table('users')
                ->join('model_has_roles', function ($j) {
                    $j->on('users.id', '=', 'model_has_roles.model_id')
                      ->where('model_has_roles.model_type', (new \App\Models\User)->getMorphClass());
                })
                ->join('roles', function ($j) {
                    $j->on('model_has_roles.role_id', '=', 'roles.id')
                      ->whereIn('roles.name', ['field_agent', 'field_executive', 'field']);
                })
                ->where('users.status', 'approved')
                ->orderBy('users.name')
                ->select('users.id', 'users.name')
                ->get()
                ->all();
        });

        return response()->json([
            'agents' => $agents,
        ]);
    }

    /**
     * POST /api/field-submissions/bulk-assign
     * Always dispatches to queue for instant response. Returns a tracking_id
     * so the frontend can poll for progress.
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $this->authorize('assignAny', FieldSubmission::class);
        $user = $request->user();

        $data = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['integer'],
            'field_executive_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ids = array_unique(array_map('intval', $data['submission_ids']));
        $agentId = (int) $data['field_executive_id'];
        $agent = User::find($agentId);

        if (! $agent || ! FieldSubmissionPolicy::isValidAssignee($agent)) {
            return response()->json([
                'message' => 'Selected assignee must be a field agent or field operations head.',
            ], 422);
        }

        $existingCount = \DB::table('field_submissions')->whereIn('id', $ids)->count();
        if ($existingCount !== count($ids)) {
            return response()->json(['message' => 'One or more submission IDs are invalid.'], 422);
        }

        $trackingId = (string) \Illuminate\Support\Str::uuid();

        \Illuminate\Support\Facades\Cache::put("bulk_assign:{$trackingId}", [
            'status' => 'pending',
            'total' => count($ids),
            'processed' => 0,
            'percent' => 0,
            'message' => 'Queued for processing...',
        ], now()->addMinutes(30));

        \App\Jobs\BulkAssignFieldAgentJob::dispatch($ids, $agentId, $user->id, $trackingId);

        return response()->json([
            'message' => count($ids) . ' submission(s) queued for assignment.',
            'queued' => true,
            'count' => count($ids),
            'tracking_id' => $trackingId,
        ]);
    }

    /**
     * GET /api/field-submissions/bulk-assign/{trackingId}/status
     * Poll for bulk assign progress.
     */
    public function bulkAssignStatus(Request $request, string $trackingId): JsonResponse
    {
        $data = \Illuminate\Support\Facades\Cache::get("bulk_assign:{$trackingId}");

        if (! $data) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json($data);
    }

    /**
     * Aggregated bootstrap: filters + columns + team/agent options + first-page data in one request.
     * Eliminates 5+ sequential API calls (filters, columns, team-options, field-agent-options, index).
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

        // Include field agent options (for assign modal) — use same cache as fieldAgentOptions()
        $fieldAgentData = [];
        $user = $request->user();
        $canAssign = $user->can('assignAny', FieldSubmission::class);

        if ($canAssign) {
            try {
                $agents = Cache::remember('field_agent_options', 600, function () {
                    return DB::table('users')
                        ->join('model_has_roles', function ($j) {
                            $j->on('users.id', '=', 'model_has_roles.model_id')
                              ->where('model_has_roles.model_type', (new User)->getMorphClass());
                        })
                        ->join('roles', function ($j) {
                            $j->on('model_has_roles.role_id', '=', 'roles.id')
                              ->whereIn('roles.name', ['field_agent', 'field_executive', 'field']);
                        })
                        ->where('users.status', 'approved')
                        ->orderBy('users.name')
                        ->select('users.id', 'users.name')
                        ->get()
                        ->all();
                });

                $fieldAgentData = ['agents' => $agents];
            } catch (\Throwable $e) {
                // silent
            }
        }

        return response()->json([
            'filters' => $filtersData,
            'columns' => $columnsData,
            'page' => $indexData,
            'team_options' => $teamData,
            'field_agent_options' => $fieldAgentData,
            'field_statuses' => array_map(fn ($s) => ['value' => $s, 'label' => $s], FieldSubmission::FIELD_STATUSES),
        ]);
    }
}
