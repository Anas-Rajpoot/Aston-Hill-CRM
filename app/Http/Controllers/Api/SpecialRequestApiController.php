<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FieldSubmissionController;
use App\Models\SpecialRequest;
use App\Models\SpecialRequestAudit;
use App\Models\SpecialRequestDocument;
use App\Rules\AllowedDocumentFile;
use App\Models\User;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SpecialRequestApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;

    private const MODULE = 'special_requests';

    private const ALLOWED_COLUMNS = [
        'id', 'company_name', 'account_number', 'request_type', 'status',
        'complete_address', 'special_instruction',
        'submitted_at', 'created_at', 'updated_at',
        'created_by', 'manager_id', 'team_leader_id', 'sales_agent_id',
    ];

    private const COMPUTED_COLUMNS = [];

    private const BASE_COLUMNS = ['id', 'status'];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SpecialRequest::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string'],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string'],
            'status' => ['sometimes', 'nullable', 'string'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:200'],
            'request_type' => ['sometimes', 'nullable', 'string'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer'],
            'manager_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';

        $dataQuery = SpecialRequest::query()->visibleTo($user);
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
        if (! empty($eagerLoad)) {
            $dataQuery->with($eagerLoad);
        }

        $offset = ($page - 1) * $perPage;
        $items = $dataQuery->skip($offset)->take($perPage)->get()->map(fn ($row) => $this->formatRow($row, $columns));

        $countCacheKey = 'special_req_count_' . $user->id . '_' . md5(json_encode($validated));
        $total = Cache::remember($countCacheKey, 30, function () use ($user, $validated) {
            $cq = SpecialRequest::query()->visibleTo($user);
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
        $allAllowed = array_merge(self::ALLOWED_COLUMNS, self::COMPUTED_COLUMNS, ['creator', 'sales_agent', 'team_leader', 'manager']);
        if (! empty($requestColumns)) {
            $allowed = array_intersect($requestColumns, $allAllowed);
            return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
        }
        $cacheKey = "col_pref_{$user->id}_" . self::MODULE;
        $preference = Cache::remember($cacheKey, 3600, fn () =>
            UserColumnPreference::where('user_id', $user->id)->where('module', self::MODULE)->first()
        );
        $cols = $preference?->visible_columns ?? config('modules.special_requests.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        $allowed = array_intersect($cols, $allAllowed);
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function applyFilters($query, array $v): void
    {
        if (! empty($v['status'])) $query->where('status', $v['status']);
        if (! empty($v['request_type'])) $query->where('request_type', $v['request_type']);
        if (! empty($v['from'])) $query->where('created_at', '>=', $v['from'] . ' 00:00:00');
        if (! empty($v['to'])) $query->where('created_at', '<=', $v['to'] . ' 23:59:59');
        if (! empty($v['submitted_from'])) $query->where('submitted_at', '>=', $v['submitted_from'] . ' 00:00:00');
        if (! empty($v['submitted_to'])) $query->where('submitted_at', '<=', $v['submitted_to'] . ' 23:59:59');
        if (! empty($v['company_name'])) {
            $query->where('company_name', 'like', '%' . addcslashes($v['company_name'], '%_\\') . '%');
        }
        if (! empty($v['account_number'])) {
            $query->where('account_number', 'like', '%' . addcslashes($v['account_number'], '%_\\') . '%');
        }
        if (! empty($v['sales_agent_id'])) $query->where('sales_agent_id', $v['sales_agent_id']);
        if (! empty($v['team_leader_id'])) $query->where('team_leader_id', $v['team_leader_id']);
        if (! empty($v['manager_id'])) $query->where('manager_id', $v['manager_id']);
        if (! empty($v['q'])) {
            $term = '%' . addcslashes($v['q'], '%_\\') . '%';
            $query->where(function ($w) use ($term) {
                $w->where('company_name', 'like', $term)
                    ->orWhere('account_number', 'like', $term)
                    ->orWhere('request_type', 'like', $term);
            });
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        if ($sort === 'creator') {
            $query->join('users as creator_users', 'special_requests.created_by', '=', 'creator_users.id')
                ->orderBy('creator_users.name', $order);
        } elseif (in_array($sort, ['sales_agent', 'team_leader', 'manager'], true)) {
            $col = $sort . '_id';
            $alias = $sort . '_users';
            $query->leftJoin("users as {$alias}", "special_requests.{$col}", '=', "{$alias}.id")
                ->orderBy("{$alias}.name", $order);
        } elseif (in_array($sort, self::ALLOWED_COLUMNS, true)) {
            $query->orderBy('special_requests.' . $sort, $order);
        }
    }

    private function buildSelectColumns(array $columns): array
    {
        $dbColumns = array_filter($columns, fn ($c) => in_array($c, self::ALLOWED_COLUMNS, true));
        $base = array_unique(array_merge(self::BASE_COLUMNS, $dbColumns));
        if (in_array('creator', $columns, true)) $base[] = 'created_by';
        foreach (['sales_agent', 'team_leader', 'manager'] as $rel) {
            $idCol = $rel . '_id';
            if (in_array($rel, $columns, true) && ! in_array($idCol, $base, true)) $base[] = $idCol;
        }
        $base = array_unique($base);
        return array_map(fn ($c) => 'special_requests.' . $c, $base);
    }

    private function formatRow(SpecialRequest $row, array $columns): array
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
            } elseif (in_array($col, ['submitted_at', 'created_at', 'updated_at'], true)) {
                $out[$col] = $row->$col ? $row->$col->format('d-M-Y H:i') : null;
            } else {
                $out[$col] = $row->$col ?? null;
            }
        }
        return $out;
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SpecialRequest::class);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'request_type' => ['required', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(SpecialRequest::STATUSES)],
            'complete_address' => ['nullable', 'string', 'max:2000'],
            'special_instruction' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['required', 'exists:users,id'],
            'team_leader_id' => ['required', 'exists:users,id'],
            'sales_agent_id' => ['required', 'exists:users,id'],
        ], [
            'company_name.required' => 'Company name is required.',
            'request_type.required' => 'Request type is required.',
            'manager_id.required' => 'Please select a manager.',
            'team_leader_id.required' => 'Please select a team leader.',
            'sales_agent_id.required' => 'Please select a sales agent.',
        ]);

        $specialRequest = SpecialRequest::create([
            ...$data,
            'created_by' => $request->user()->id,
            'status' => $data['status'] ?? 'submitted',
            'submitted_at' => now(),
        ]);

        // Handle document uploads
        $files = $request->file('documents', []);
        if (is_array($files)) {
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('special-request-documents', 'public');
                    SpecialRequestDocument::create([
                        'special_request_id' => $specialRequest->id,
                        'doc_key' => 'additional_document',
                        'label' => 'Additional Document',
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }
        }

        return response()->json([
            'id' => $specialRequest->id,
            'message' => 'Special request submitted successfully.',
        ], 201);
    }

    public function show(Request $request, SpecialRequest $specialRequest): JsonResponse
    {
        $this->authorize('view', $specialRequest);

        $specialRequest->load([
            'creator:id,name',
            'manager:id,name',
            'teamLeader:id,name',
            'salesAgent:id,name',
            'documents',
        ]);

        $documents = $specialRequest->documents->map(fn ($d) => [
            'id' => $d->id,
            'doc_key' => $d->doc_key,
            'label' => $d->label ?? $d->doc_key,
            'file_path' => $d->file_path,
            'original_name' => $d->file_name,
            'mime' => $d->mime,
            'size' => $d->size,
        ])->values()->all();

        return response()->json([
            'id' => $specialRequest->id,
            'company_name' => $specialRequest->company_name,
            'account_number' => $specialRequest->account_number,
            'request_type' => $specialRequest->request_type,
            'status' => $specialRequest->status,
            'complete_address' => $specialRequest->complete_address,
            'special_instruction' => $specialRequest->special_instruction,
            'manager_id' => $specialRequest->manager_id,
            'team_leader_id' => $specialRequest->team_leader_id,
            'sales_agent_id' => $specialRequest->sales_agent_id,
            'submitted_at' => $specialRequest->submitted_at?->toIso8601String(),
            'created_at' => $specialRequest->created_at?->toIso8601String(),
            'updated_at' => $specialRequest->updated_at?->toIso8601String(),
            'manager_name' => $specialRequest->manager?->name,
            'team_leader_name' => $specialRequest->teamLeader?->name,
            'sales_agent_name' => $specialRequest->salesAgent?->name,
            'creator_name' => $specialRequest->creator?->name,
            'documents' => $documents,
        ]);
    }

    public function uploadDocuments(Request $request, SpecialRequest $specialRequest): JsonResponse
    {
        $this->authorize('update', $specialRequest);

        $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['required', 'file', new AllowedDocumentFile()],
        ]);

        $files = $request->file('documents', []);
        if (is_array($files)) {
            foreach ($files as $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }
                $path = $file->store('special-request-documents', 'public');
                SpecialRequestDocument::create([
                    'special_request_id' => $specialRequest->id,
                    'doc_key' => 'additional_document',
                    'label' => 'Additional Document',
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        return response()->json(['message' => 'Documents added.']);
    }

    public function deleteDocument(Request $request, SpecialRequest $specialRequest, int $document): JsonResponse
    {
        $this->authorize('update', $specialRequest);

        $doc = SpecialRequestDocument::query()
            ->where('special_request_id', $specialRequest->id)
            ->where('id', $document)
            ->first();

        if (! $doc) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        if (! empty($doc->file_path) && Storage::disk('public')->exists($doc->file_path)) {
            Storage::disk('public')->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['message' => 'Document removed.']);
    }

    public function update(Request $request, SpecialRequest $specialRequest): JsonResponse
    {
        $this->authorize('update', $specialRequest);

        $data = $request->validate([
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'request_type' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(SpecialRequest::STATUSES)],
            'complete_address' => ['nullable', 'string', 'max:2000'],
            'special_instruction' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['sometimes', 'required', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'required', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'required', 'exists:users,id'],
        ]);

        $specialRequest->update($data);

        return response()->json([
            'id' => $specialRequest->id,
            'message' => 'Special request updated.',
        ]);
    }

    public function patch(Request $request, SpecialRequest $specialRequest): JsonResponse
    {
        $this->authorize('update', $specialRequest);

        $data = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'request_type' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(SpecialRequest::STATUSES)],
            'complete_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'special_instruction' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'manager_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        if (! empty($data)) {
            $specialRequest->update($data);
        }

        $specialRequest->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'creator:id,name']);
        $columns = array_merge(
            ['id', 'company_name', 'account_number', 'request_type', 'status', 'complete_address', 'special_instruction', 'manager', 'team_leader', 'sales_agent', 'creator', 'updated_at'],
            array_keys($data)
        );
        $row = $this->formatRow($specialRequest, array_unique($columns));

        return response()->json([
            'id' => $specialRequest->id,
            'message' => 'Updated.',
            'row' => $row,
        ]);
    }

    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', SpecialRequest::class);

        return response()->json([
            'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)], SpecialRequest::STATUSES),
            'request_types' => array_map(fn ($t) => ['value' => $t, 'label' => $t], SpecialRequest::REQUEST_TYPES),
        ]);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SpecialRequest::class);

        $config = config('modules.special_requests.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            $allColumns[] = ['key' => $key, 'label' => $def['label'] ?? $key];
        }

        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', self::MODULE)->first();
        $visible = $pref?->visible_columns ?? config('modules.special_requests.default_columns', []);

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SpecialRequest::class);

        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string'],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );

        Cache::forget("col_pref_{$request->user()->id}_" . self::MODULE);

        return response()->json(['success' => true]);
    }

    public function audits(Request $request, SpecialRequest $specialRequest): JsonResponse
    {
        $this->authorize('view', $specialRequest);

        $rows = SpecialRequestAudit::query()
            ->where('special_request_id', $specialRequest->id)
            ->with('changedByUser:id,name')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $data = $rows->map(fn (SpecialRequestAudit $audit) => [
            'id' => $audit->id,
            'field_name' => $audit->field_name,
            'old_value' => $audit->old_value,
            'new_value' => $audit->new_value,
            'changed_at' => $audit->changed_at?->toIso8601String(),
            'changed_by' => $audit->changed_by,
            'changed_by_name' => $audit->changedByUser?->name ?? '—',
        ]);

        $data = $this->resolveAuditDisplayValues($data);

        return response()->json(['data' => $data]);
    }

    public function downloadDocument(Request $request, SpecialRequest $specialRequest, int $document)
    {
        $this->authorize('view', $specialRequest);

        $doc = SpecialRequestDocument::where('special_request_id', $specialRequest->id)
            ->where('id', $document)->first();
        if (! $doc || ! $doc->file_path) {
            return response()->json(['message' => 'Document not found.'], 404);
        }
        $fullPath = Storage::disk('public')->path($doc->file_path);
        if (! is_file($fullPath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        return response()->file($fullPath, [
            'Content-Disposition' => 'attachment; filename="' . addslashes($doc->file_name) . '"',
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $filtersData = json_decode($this->filters()->getContent(), true);
        $columnsData = json_decode($this->columns($request)->getContent(), true);
        $indexData = json_decode($this->index($request)->getContent(), true);

        $teamData = [];
        try {
            $teamResponse = app(FieldSubmissionController::class)->teamOptions($request);
            $teamData = json_decode($teamResponse->getContent(), true) ?? [];
        } catch (\Throwable $e) {}

        return response()->json([
            'filters' => $filtersData,
            'columns' => $columnsData,
            'page' => $indexData,
            'team_options' => $teamData,
        ]);
    }
}
