<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FieldSubmissionController;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientAlert;
use App\Models\ClientCompanyDetail;
use App\Models\ClientContact;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ClientApiController extends Controller
{
    private const MODULE = 'clients';

    private const ALLOWED_COLUMNS = [
        'id', 'company_name', 'account_number', 'submitted_at',
        'manager_id', 'team_leader_id', 'sales_agent_id',
        'status', 'service_type', 'product_type', 'address', 'product_name',
        'mrc', 'quantity', 'other', 'migration_numbers', 'fiber',
        'order_number', 'wo_number', 'completion_date', 'payment_connection',
        'contract_type', 'contract_end_date', 'renewal_alert', 'additional_notes',
        'created_by', 'revenue', 'csr_name_1', 'csr_name_2', 'csr_name_3',
    ];

    private const BASE_COLUMNS = ['id', 'status'];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']))],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Client::STATUSES)],
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
        $sort = $validated['sort'] ?? config('modules.clients.default_sort.0', 'submitted_at');
        $order = $validated['order'] ?? config('modules.clients.default_sort.1', 'desc');

        $baseQuery = Client::query();
        $this->applyFilters($baseQuery, $validated);
        $total = $baseQuery->count();

        $dataQuery = Client::query();
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

        $cols = $preference?->visible_columns ?? config('modules.clients.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        $allowed = array_intersect($cols, $allAllowed);
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function applyFilters($query, array $validated): void
    {
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
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
        if (! empty($validated['manager_id'])) {
            $query->where('manager_id', $validated['manager_id']);
        }
        if (! empty($validated['team_leader_id'])) {
            $query->where('team_leader_id', $validated['team_leader_id']);
        }
        if (! empty($validated['sales_agent_id'])) {
            $query->where('sales_agent_id', $validated['sales_agent_id']);
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        $direction = strtolower($order) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'creator') {
            $query->leftJoin('users as creator_users', 'clients.created_by', '=', 'creator_users.id')
                ->orderBy('creator_users.name', $direction);
            return;
        }
        if (in_array($sort, ['manager', 'team_leader', 'sales_agent'], true)) {
            $col = $sort === 'manager' ? 'manager_id' : ($sort === 'team_leader' ? 'team_leader_id' : 'sales_agent_id');
            $alias = $sort . '_users';
            $query->leftJoin("users as {$alias}", "clients.{$col}", '=', "{$alias}.id")
                ->orderBy("{$alias}.name", $direction);
            return;
        }
        $query->orderBy('clients.' . $sort, $direction);
    }

    private function buildSelectColumns(array $columns): array
    {
        $base = ['clients.id', 'clients.status'];
        $map = [
            'company_name' => 'clients.company_name',
            'account_number' => 'clients.account_number',
            'submitted_at' => 'clients.submitted_at',
            'manager_id' => 'clients.manager_id',
            'manager' => 'clients.manager_id',
            'team_leader_id' => 'clients.team_leader_id',
            'team_leader' => 'clients.team_leader_id',
            'sales_agent_id' => 'clients.sales_agent_id',
            'sales_agent' => 'clients.sales_agent_id',
            'service_type' => 'clients.service_type',
            'product_type' => 'clients.product_type',
            'address' => 'clients.address',
            'product_name' => 'clients.product_name',
            'mrc' => 'clients.mrc',
            'quantity' => 'clients.quantity',
            'other' => 'clients.other',
            'migration_numbers' => 'clients.migration_numbers',
            'fiber' => 'clients.fiber',
            'order_number' => 'clients.order_number',
            'wo_number' => 'clients.wo_number',
            'completion_date' => 'clients.completion_date',
            'payment_connection' => 'clients.payment_connection',
            'contract_type' => 'clients.contract_type',
            'contract_end_date' => 'clients.contract_end_date',
            'renewal_alert' => 'clients.renewal_alert',
            'additional_notes' => 'clients.additional_notes',
            'created_by' => 'clients.created_by',
            'creator' => 'clients.created_by',
        ];
        $optionalColumns = ['revenue' => 'clients.revenue', 'csr_name_1' => 'clients.csr_name_1', 'csr_name_2' => 'clients.csr_name_2', 'csr_name_3' => 'clients.csr_name_3'];
        foreach ($optionalColumns as $key => $select) {
            if (Schema::hasColumn('clients', $key)) {
                $map[$key] = $select;
            }
        }
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

    private function formatRow(Client $row, array $columns): array
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
            if (in_array($col, ['submitted_at'], true)) {
                $out[$col] = $row->$col ? $row->$col->format('d/m/Y H:i') : null;
                continue;
            }
            if (in_array($col, ['completion_date', 'contract_end_date'], true)) {
                $out[$col] = $row->$col ? $row->$col->format('d/m/Y') : null;
                continue;
            }
            $out[$col] = $row->$col ?? null;
        }
        return $out;
    }

    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $teamOptions = app(FieldSubmissionController::class)->teamOptions(request());
        $teamData = $teamOptions->getData(true);
        $managers = $teamData['managers'] ?? [];
        $teamLeaders = $teamData['team_leaders'] ?? [];
        $salesAgents = $teamData['sales_agents'] ?? [];

        return response()->json([
            'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => ucfirst(str_replace('_', ' ', $s))], Client::STATUSES),
            'managers' => $managers,
            'team_leaders' => $teamLeaders,
            'sales_agents' => $salesAgents,
        ]);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $config = config('modules.clients.columns', []);
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

        $defaultColumns = config('modules.clients.default_columns', []);
        $visible = $pref?->visible_columns ?? $defaultColumns;

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
            'default_columns' => $defaultColumns,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

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

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $client->load([
            'manager:id,name',
            'teamLeader:id,name',
            'salesAgent:id,name',
            'creator:id,name',
            'companyDetail',
            'contacts',
            'addresses',
        ]);

        $companyDetail = $client->companyDetail;
        $base = [
            'id' => $client->id,
            'company_name' => $client->company_name,
            'account_number' => $client->account_number,
            'submitted_at' => $client->submitted_at?->toIso8601String(),
            'manager_id' => $client->manager_id,
            'manager' => $client->manager?->name,
            'team_leader_id' => $client->team_leader_id,
            'team_leader' => $client->teamLeader?->name,
            'sales_agent_id' => $client->sales_agent_id,
            'sales_agent' => $client->salesAgent?->name,
            'status' => $client->status,
            'service_type' => $client->service_type,
            'product_type' => $client->product_type,
            'address' => $client->address,
            'product_name' => $client->product_name,
            'mrc' => $client->mrc,
            'quantity' => $client->quantity,
            'other' => $client->other,
            'migration_numbers' => $client->migration_numbers,
            'fiber' => $client->fiber,
            'order_number' => $client->order_number,
            'wo_number' => $client->wo_number,
            'completion_date' => $client->completion_date?->format('Y-m-d'),
            'payment_connection' => $client->payment_connection,
            'contract_type' => $client->contract_type,
            'contract_end_date' => $client->contract_end_date?->format('Y-m-d'),
            'renewal_alert' => $client->renewal_alert,
            'additional_notes' => $client->additional_notes,
            'created_by' => $client->created_by,
            'creator' => $client->creator?->name,
            'revenue' => $client->revenue ? (float) $client->revenue : null,
            'csr_name_1' => $client->csr_name_1,
            'csr_name_2' => $client->csr_name_2,
            'csr_name_3' => $client->csr_name_3,
            'company_detail' => $companyDetail ? [
                'trade_license_issuing_authority' => $companyDetail->trade_license_issuing_authority,
                'company_category' => $companyDetail->company_category,
                'trade_license_number' => $companyDetail->trade_license_number,
                'trade_license_expiry_date' => $companyDetail->trade_license_expiry_date?->format('Y-m-d'),
                'establishment_card_number' => $companyDetail->establishment_card_number,
                'establishment_card_expiry_date' => $companyDetail->establishment_card_expiry_date?->format('Y-m-d'),
                'account_taken_from' => $companyDetail->account_taken_from,
                'account_mapping_date' => $companyDetail->account_mapping_date?->format('Y-m-d'),
                'account_transfer_given_to' => $companyDetail->account_transfer_given_to,
                'account_transfer_given_date' => $companyDetail->account_transfer_given_date?->format('Y-m-d'),
                'account_manager_name' => $companyDetail->account_manager_name,
                'csr_name_1' => $companyDetail->csr_name_1,
                'csr_name_2' => $companyDetail->csr_name_2,
                'csr_name_3' => $companyDetail->csr_name_3,
                'first_bill' => $companyDetail->first_bill,
                'second_bill' => $companyDetail->second_bill,
                'third_bill' => $companyDetail->third_bill,
                'fourth_bill' => $companyDetail->fourth_bill,
                'additional_comment_1' => $companyDetail->additional_comment_1,
                'additional_comment_2' => $companyDetail->additional_comment_2,
            ] : null,
            'contacts' => $client->contacts->map(fn ($c) => [
                'id' => $c->id,
                'sort_order' => $c->sort_order,
                'name' => $c->name,
                'designation' => $c->designation,
                'contact_number' => $c->contact_number,
                'alternate_number' => $c->alternate_number,
                'email' => $c->email,
                'as_updated_or_not' => $c->as_updated_or_not,
                'as_expiry_date' => $c->as_expiry_date?->format('Y-m-d'),
                'additional_note' => $c->additional_note,
            ])->values()->all(),
            'addresses' => $client->addresses->map(fn ($a) => [
                'id' => $a->id,
                'sort_order' => $a->sort_order,
                'full_address' => $a->full_address,
                'unit' => $a->unit,
                'building' => $a->building,
                'area' => $a->area,
                'emirates' => $a->emirates,
            ])->values()->all(),
        ];

        return response()->json($base);
    }

    /**
     * Products & Services: clients with same account_number (excluding current if needed, or include all).
     */
    public function products(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string'],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $accountNumber = $client->account_number;
        if (empty($accountNumber)) {
            $query = Client::where('id', $client->id);
        } else {
            $query = Client::where('account_number', $accountNumber);
        }

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'submitted_at';
        $order = strtolower($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->with(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name']);
        $query->orderBy($sort, $order);

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);
        $items = $paginated->getCollection()->map(function ($row) {
            return $this->formatRow($row, array_merge(self::BASE_COLUMNS, self::ALLOWED_COLUMNS));
        });
        $paginated->setCollection($items);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * VAS requests for this client's account_number.
     */
    public function vasRequests(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $accountNumber = $client->account_number;
        $query = \App\Models\VasRequestSubmission::query()
            ->when($accountNumber !== null && $accountNumber !== '', fn ($q) => $q->where('account_number', $accountNumber))
            ->when(empty($accountNumber), fn ($q) => $q->whereRaw('1 = 0'));

        $query->with(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name']);
        $query->orderByDesc('submitted_at');

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginated->getCollection()->map(function ($row) {
            return [
                'id' => $row->id,
                'request_id' => 'VAS-' . $row->id,
                'submitted_at' => $row->submitted_at?->format('d-M-Y'),
                'request_type' => $row->request_type,
                'status' => $row->status,
                'company_name' => $row->company_name,
                'account_number' => $row->account_number,
                'description' => $row->description,
                'manager' => $row->manager?->name,
                'team_leader' => $row->teamLeader?->name,
                'sales_agent' => $row->salesAgent?->name,
            ];
        });
        $paginated->setCollection($items);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Customer support submissions for this client's account_number.
     */
    public function customerSupport(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $accountNumber = $client->account_number;
        $query = \App\Models\CustomerSupportSubmission::query()
            ->when($accountNumber !== null && $accountNumber !== '', fn ($q) => $q->where('account_number', $accountNumber))
            ->when(empty($accountNumber), fn ($q) => $q->whereRaw('1 = 0'));

        $query->with(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name']);
        $query->orderByDesc('submitted_at');

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginated->getCollection()->map(function ($row) {
            return [
                'id' => $row->id,
                'ticket_id' => 'CS-' . $row->id,
                'submitted_at' => $row->submitted_at?->format('d-M-Y'),
                'issue_category' => $row->issue_category,
                'status' => $row->status,
                'company_name' => $row->company_name,
                'account_number' => $row->account_number,
                'contact_number' => $row->contact_number,
                'issue_description' => $row->issue_description,
                'manager' => $row->manager?->name,
                'team_leader' => $row->teamLeader?->name,
                'sales_agent' => $row->salesAgent?->name,
            ];
        });
        $paginated->setCollection($items);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Change history for this client (old value, new value, time, person).
     */
    public function audits(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $query = $client->audits()->with('changedByUser:id,name');
        $query->orderByDesc('changed_at');

        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);
        $paginated = $query->paginate($perPage);

        $items = $paginated->getCollection()->map(fn ($a) => [
            'id' => $a->id,
            'field_name' => $a->field_name,
            'old_value' => $a->old_value,
            'new_value' => $a->new_value,
            'changed_at' => $a->changed_at->toIso8601String(),
            'changed_by' => $a->changedByUser?->name,
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(Client::STATUSES)],
            'revenue' => ['sometimes', 'nullable', 'numeric'],
            'csr_name_1' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_2' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_3' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $client->update($validated);

        return response()->json(['success' => true]);
    }

    public function updateCompanyDetails(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'trade_license_issuing_authority' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trade_license_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trade_license_expiry_date' => ['sometimes', 'nullable', 'date'],
            'establishment_card_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'establishment_card_expiry_date' => ['sometimes', 'nullable', 'date'],
            'account_taken_from' => ['sometimes', 'nullable', 'string', 'max:100'],
            'account_mapping_date' => ['sometimes', 'nullable', 'date'],
            'account_transfer_given_to' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_transfer_given_date' => ['sometimes', 'nullable', 'date'],
            'account_manager_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'csr_name_1' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_2' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_3' => ['sometimes', 'nullable', 'string', 'max:100'],
            'first_bill' => ['sometimes', 'nullable', 'string', 'max:50'],
            'second_bill' => ['sometimes', 'nullable', 'string', 'max:50'],
            'third_bill' => ['sometimes', 'nullable', 'string', 'max:50'],
            'fourth_bill' => ['sometimes', 'nullable', 'string', 'max:50'],
            'additional_comment_1' => ['sometimes', 'nullable', 'string'],
            'additional_comment_2' => ['sometimes', 'nullable', 'string'],
        ]);

        ClientCompanyDetail::updateOrCreate(
            ['client_id' => $client->id],
            array_merge($validated, ['client_id' => $client->id])
        );

        return response()->json(['success' => true]);
    }

    public function updateContacts(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'contacts' => ['required', 'array'],
            'contacts.*.id' => ['sometimes', 'nullable', 'integer', 'exists:client_contacts,id'],
            'contacts.*.name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'contacts.*.designation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contacts.*.contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contacts.*.alternate_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contacts.*.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contacts.*.as_updated_or_not' => ['sometimes', 'nullable', 'string', 'max:20'],
            'contacts.*.as_expiry_date' => ['sometimes', 'nullable', 'date'],
            'contacts.*.additional_note' => ['sometimes', 'nullable', 'string'],
        ]);

        $idsToKeep = [];
        foreach ($validated['contacts'] as $i => $row) {
            $data = collect($row)->except('id')->filter(fn ($v) => $v !== null && $v !== '')->all();
            $data['client_id'] = $client->id;
            $data['sort_order'] = $i;
            if (! empty($row['id'])) {
                $contact = ClientContact::where('client_id', $client->id)->find($row['id']);
                if ($contact) {
                    $contact->update($data);
                    $idsToKeep[] = $contact->id;
                    continue;
                }
            }
            $contact = ClientContact::create($data);
            $idsToKeep[] = $contact->id;
        }

        ClientContact::where('client_id', $client->id)->whereNotIn('id', $idsToKeep)->delete();

        return response()->json(['success' => true]);
    }

    public function updateAddresses(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'addresses' => ['required', 'array'],
            'addresses.*.id' => ['sometimes', 'nullable', 'integer', 'exists:client_addresses,id'],
            'addresses.*.full_address' => ['sometimes', 'nullable', 'string'],
            'addresses.*.unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'addresses.*.building' => ['sometimes', 'nullable', 'string', 'max:200'],
            'addresses.*.area' => ['sometimes', 'nullable', 'string', 'max:200'],
            'addresses.*.emirates' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $idsToKeep = [];
        foreach ($validated['addresses'] as $i => $row) {
            $data = collect($row)->except('id')->filter(fn ($v) => $v !== null && $v !== '')->all();
            $data['client_id'] = $client->id;
            $data['sort_order'] = $i;
            if (! empty($row['id'])) {
                $address = ClientAddress::where('client_id', $client->id)->find($row['id']);
                if ($address) {
                    $address->update($data);
                    $idsToKeep[] = $address->id;
                    continue;
                }
            }
            $address = ClientAddress::create($data);
            $idsToKeep[] = $address->id;
        }

        ClientAddress::where('client_id', $client->id)->whereNotIn('id', $idsToKeep)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Alerts for this client.
     */
    public function alerts(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $query = $client->alerts()->with('manager:id,name');
        $query->orderBy('expiry_date');

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        $paginated = $query->paginate($perPage);

        $items = $paginated->getCollection()->map(fn ($a) => [
            'id' => $a->id,
            'alert_type' => $a->alert_type,
            'company_name' => $a->company_name,
            'account_number' => $a->account_number,
            'expiry_date' => $a->expiry_date?->format('d-M-Y'),
            'days_remaining' => $a->days_remaining,
            'manager' => $a->manager?->name,
            'status' => $a->status,
            'created_date' => $a->created_date?->format('d-M-Y'),
            'resolved' => $a->resolved,
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
