<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FieldSubmissionController;
use App\Models\Client;
use App\Models\ClientAlert;
use App\Models\ClientAddress;
use App\Models\ClientCompanyDetail;
use App\Models\ClientContact;
use App\Models\ClientCsr;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserColumnPreference;
use App\Repositories\ClientRepository;
use App\Repositories\FilterRepository;
use App\Http\Resources\ClientResource;
use App\Http\Resources\FilterResource;
use App\Services\CacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;
    private const MODULE = 'clients';
    private const ALLOWED_PREF_MODULES = ['clients', 'all-clients', 'client-profile-products', 'order-status', 'order-status-listing'];
    private const COUNT_CACHE_SECONDS = 30;
    private const AUTO_RENEWAL_ALERT_TYPES = [
        'Trade License Expiry',
        'Establishment Card Expiry',
    ];

    private const ALLOWED_COLUMNS = [
        'id', 'company_name', 'account_number', 'submitted_at',
        'manager_id', 'team_leader_id', 'sales_agent_id',
        'submission_type', 'service_category',
        'status', 'service_type', 'product_type', 'address', 'product_name',
        'mrc', 'quantity', 'other', 'migration_numbers', 'activity', 'fiber',
        'order_number', 'wo_number', 'work_order_status', 'activation_date', 'completion_date', 'payment_connection',
        'contract_type', 'contract_end_date', 'clawback_chum', 'remarks', 'renewal_alert', 'additional_notes',
        'trade_license_issuing_authority', 'company_category', 'trade_license_number', 'trade_license_expiry_date',
        'establishment_card_number', 'establishment_card_expiry_date',
        'account_taken_from', 'account_mapping_date', 'account_transfer_given_to', 'account_transfer_given_date',
        'account_manager_name', 'first_bill', 'second_bill', 'third_bill', 'fourth_bill',
        'additional_comment_1', 'additional_comment_2', 'full_address',
        'created_by', 'revenue', 'csr_name_1', 'csr_name_2', 'csr_name_3',
    ];

    private const BASE_COLUMNS = ['id', 'status'];
    /** @var array<string,bool>|null */
    private static ?array $clientTableColumns = null;

    private function normalizeAccountNumber(?string $accountNumber): ?string
    {
        $value = trim((string) ($accountNumber ?? ''));
        return $value === '' ? null : $value;
    }

    private function ensureAccountNumberIsUnique(?string $accountNumber, ?int $ignoreClientId = null): void
    {
        $normalized = $this->normalizeAccountNumber($accountNumber);
        if ($normalized === null) {
            return;
        }

        $query = Client::query()
            ->whereRaw('LOWER(TRIM(account_number)) = ?', [strtolower($normalized)]);

        if ($ignoreClientId !== null) {
            $query->where('id', '!=', $ignoreClientId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'account_number' => 'This account number is already assigned to another client. 1 account can only be linked to 1 client.',
            ]);
        }
    }

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * GET /api/clients
     *
     * Query params: cursor, per_page, sort, order, columns[], and field/date filters.
     * Response shape: { success: bool, data: array<ClientRow>, message: string, meta: { per_page, next_cursor, prev_cursor, has_more } }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);
        $this->enableLocalQueryProfile();

        $validated = $request->validate([
            'cursor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'], // backward-compat only
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']))],
            'activity' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'wo_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'submission_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'product_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'work_order_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'payment_connection' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'clawback_chum' => ['sometimes', 'nullable', 'string', 'max:10'],
            'company_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trade_license_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'establishment_card_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'account_manager_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'csr_name_1' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Client::STATUSES)],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'activation_from' => ['sometimes', 'nullable', 'date'],
            'activation_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:activation_from'],
            'completion_from' => ['sometimes', 'nullable', 'date'],
            'completion_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:completion_from'],
            'contract_end_from' => ['sometimes', 'nullable', 'date'],
            'contract_end_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:contract_end_from'],
            'trade_license_expiry_from' => ['sometimes', 'nullable', 'date'],
            'trade_license_expiry_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:trade_license_expiry_from'],
            'establishment_card_expiry_from' => ['sometimes', 'nullable', 'date'],
            'establishment_card_expiry_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:establishment_card_expiry_from'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'alert_type' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null, $this->resolvePrefModule($request));
        $validated['per_page'] = (int) ($validated['per_page'] ?? 20);

        $cacheParams = $validated;
        $cacheParams['columns'] = $columns;
        $payload = $this->rememberByTags(
            CacheKey::make('clients:index', $cacheParams, $user),
            120,
            function () use ($validated, $columns, $user) {
                $paginator = app(ClientRepository::class)->list($validated, $columns, $user);
                $items = ClientResource::collection(collect($paginator->items()))->resolve();

                return [
                    'success' => true,
                    'data' => $items,
                    'message' => 'Clients fetched successfully.',
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        // Keep compatibility keys expected by older clients.
                        'next_cursor' => null,
                        'prev_cursor' => null,
                        'has_more' => $paginator->hasMorePages(),
                    ],
                ];
            }
        );

        $response = response()->json($payload, 200);

        $response = $this->withCacheHeaders($response, 'clients:last-modified');
        $this->logLocalSlowQueries($request->path());

        return $response;
    }

    private function resolveColumns($user, ?array $requestColumns, ?string $module = null): array
    {
        $prefModule = $module ?: self::MODULE;
        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']);
        if (! empty($requestColumns)) {
            $allowed = array_intersect($requestColumns, $allAllowed);
            return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
        }

        $cacheKey = "col_pref_{$user->id}_" . $prefModule;
        $preference = Cache::remember($cacheKey, 3600, function () use ($user, $prefModule) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', $prefModule)
                ->first();
        });

        $cols = $preference?->visible_columns ?? config('modules.clients.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        $allowed = array_intersect($cols, $allAllowed);
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function resolvePrefModule(Request $request): string
    {
        $module = (string) $request->input('module', self::MODULE);
        return in_array($module, self::ALLOWED_PREF_MODULES, true) ? $module : self::MODULE;
    }

    private function sumMrcByStatus(Client $client, string $status): float
    {
        // This is now called via revenueByStatus() which batches all 3 statuses in one query
        $accountNumber = trim((string) $client->account_number);
        $query = Client::where('status', $status);
        if ($accountNumber !== '') {
            $query->where('account_number', $accountNumber);
        } else {
            $query->where('id', $client->id);
        }
        return (float) $query->sum('mrc');
    }

    /**
     * Get revenue breakdown by status in a single query instead of 3 separate SUM queries.
     */
    private function revenueByStatus(Client $client): array
    {
        $accountNumber = trim((string) $client->account_number);
        $query = Client::query();
        if ($accountNumber !== '') {
            $query->where('account_number', $accountNumber);
        } else {
            $query->where('id', $client->id);
        }
        $row = $query->selectRaw("
            SUM(CASE WHEN status = 'Normal' THEN mrc ELSE 0 END) as normal_revenue,
            SUM(CASE WHEN status = 'Churn' THEN mrc ELSE 0 END) as churn_revenue,
            SUM(CASE WHEN status = 'Clawback' THEN mrc ELSE 0 END) as clawback_revenue
        ")->first();

        return [
            'normal_revenue' => (float) ($row->normal_revenue ?? 0),
            'churn_revenue' => (float) ($row->churn_revenue ?? 0),
            'clawback_revenue' => (float) ($row->clawback_revenue ?? 0),
        ];
    }

    private function applyFilters($query, array $validated): void
    {
        $applyLike = static function ($q, string $column, ?string $value): void {
            if ($value === null || trim($value) === '') {
                return;
            }
            $term = '%' . addcslashes($value, '%_\\') . '%';
            $q->where($column, 'like', $term);
        };
        $applyDateRange = static function ($q, string $column, ?string $from, ?string $to): void {
            if (! empty($from)) {
                $q->whereDate($column, '>=', $from);
            }
            if (! empty($to)) {
                $q->whereDate($column, '<=', $to);
            }
        };

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        $applyDateRange($query, 'submitted_at', $validated['submitted_from'] ?? null, $validated['submitted_to'] ?? null);
        $applyDateRange($query, 'activation_date', $validated['activation_from'] ?? null, $validated['activation_to'] ?? null);
        $applyDateRange($query, 'completion_date', $validated['completion_from'] ?? null, $validated['completion_to'] ?? null);
        $applyDateRange($query, 'contract_end_date', $validated['contract_end_from'] ?? null, $validated['contract_end_to'] ?? null);

        $applyLike($query, 'company_name', $validated['company_name'] ?? null);
        $applyLike($query, 'account_number', $validated['account_number'] ?? null);
        $applyLike($query, 'activity', $validated['activity'] ?? null);
        $applyLike($query, 'wo_number', $validated['wo_number'] ?? null);
        $applyLike($query, 'submission_type', $validated['submission_type'] ?? null);
        $applyLike($query, 'service_category', $validated['service_category'] ?? null);
        $applyLike($query, 'service_type', $validated['service_type'] ?? null);
        $applyLike($query, 'product_type', $validated['product_type'] ?? null);
        $applyLike($query, 'product_name', $validated['product_name'] ?? null);
        $applyLike($query, 'work_order_status', $validated['work_order_status'] ?? null);
        $applyLike($query, 'payment_connection', $validated['payment_connection'] ?? null);
        $applyLike($query, 'contract_type', $validated['contract_type'] ?? null);
        $applyLike($query, 'clawback_chum', $validated['clawback_chum'] ?? null);
        $applyLike($query, 'csr_name_1', $validated['csr_name_1'] ?? null);

        if (! empty($validated['manager_id'])) {
            $query->where('manager_id', $validated['manager_id']);
        }
        if (! empty($validated['team_leader_id'])) {
            $query->where('team_leader_id', $validated['team_leader_id']);
        }
        if (! empty($validated['sales_agent_id'])) {
            $query->where('sales_agent_id', $validated['sales_agent_id']);
        }
        if (! empty($validated['alert_type'])) {
            $query->whereHas('alerts', function ($q) use ($validated) {
                $q->where('alert_type', $validated['alert_type']);
            });
        }

        $companyDetailTextFilters = [
            'company_category' => $validated['company_category'] ?? null,
            'trade_license_number' => $validated['trade_license_number'] ?? null,
            'establishment_card_number' => $validated['establishment_card_number'] ?? null,
            'account_manager_name' => $validated['account_manager_name'] ?? null,
        ];
        $hasCompanyDetailTextFilters = collect($companyDetailTextFilters)->contains(
            fn ($v) => $v !== null && trim((string) $v) !== ''
        );
        $hasCompanyDetailDateFilters = ! empty($validated['trade_license_expiry_from'])
            || ! empty($validated['trade_license_expiry_to'])
            || ! empty($validated['establishment_card_expiry_from'])
            || ! empty($validated['establishment_card_expiry_to']);
        if ($hasCompanyDetailTextFilters || $hasCompanyDetailDateFilters) {
            $query->whereHas('companyDetail', function ($q) use ($companyDetailTextFilters, $validated, $applyLike, $applyDateRange) {
                foreach ($companyDetailTextFilters as $column => $value) {
                    $applyLike($q, $column, $value);
                }
                $applyDateRange(
                    $q,
                    'trade_license_expiry_date',
                    $validated['trade_license_expiry_from'] ?? null,
                    $validated['trade_license_expiry_to'] ?? null
                );
                $applyDateRange(
                    $q,
                    'establishment_card_expiry_date',
                    $validated['establishment_card_expiry_from'] ?? null,
                    $validated['establishment_card_expiry_to'] ?? null
                );
            });
        }
    }

    private function isInCurrentOrNextMonth(?Carbon $date, Carbon $now): bool
    {
        if (! $date) {
            return false;
        }
        if ((int) $date->year === (int) $now->year && (int) $date->month === (int) $now->month) {
            return true;
        }
        $nextMonth = $now->copy()->addMonthNoOverflow();
        return (int) $date->year === (int) $nextMonth->year
            && (int) $date->month === (int) $nextMonth->month;
    }

    private function resolveRenewalAlertCandidates(Client $client, Carbon $now): array
    {
        $candidates = [];
        $companyDetail = $client->companyDetail;

        $dateMap = [
            'Trade License Expiry' => $companyDetail?->trade_license_expiry_date,
            'Establishment Card Expiry' => $companyDetail?->establishment_card_expiry_date,
        ];

        foreach ($dateMap as $type => $date) {
            if (! $date || ! $this->isInCurrentOrNextMonth($date, $now)) {
                continue;
            }
            $candidates[] = [
                'alert_type' => $type,
                'expiry_date' => $date->copy()->startOfDay(),
            ];
        }

        return $candidates;
    }

    private function syncRenewalAlertsForClientIds(array $clientIds): void
    {
        if (empty($clientIds)) {
            return;
        }

        $now = now()->startOfDay();
        $clients = Client::query()
            ->with(['companyDetail:id,client_id,trade_license_expiry_date,establishment_card_expiry_date'])
            ->whereIn('id', $clientIds)
            ->get(['id', 'company_name', 'account_number', 'manager_id', 'activation_date', 'contract_end_date']);

        $existingAuto = ClientAlert::query()
            ->whereIn('client_id', $clientIds)
            ->whereNull('created_by')
            ->where('resolved', false)
            ->whereIn('alert_type', self::AUTO_RENEWAL_ALERT_TYPES)
            ->get(['id', 'client_id', 'alert_type'])
            ->groupBy('client_id');

        foreach ($clients as $client) {
            $candidates = $this->resolveRenewalAlertCandidates($client, $now);
            $candidateTypes = collect($candidates)->pluck('alert_type')->all();

            foreach ($candidates as $candidate) {
                $daysRemaining = $now->diffInDays($candidate['expiry_date'], false);
                ClientAlert::updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'alert_type' => $candidate['alert_type'],
                        'resolved' => false,
                        'created_by' => null,
                    ],
                    [
                        'company_name' => $client->company_name,
                        'account_number' => $client->account_number,
                        'expiry_date' => $candidate['expiry_date']->toDateString(),
                        'days_remaining' => $daysRemaining >= 0 ? $daysRemaining : 0,
                        'manager_id' => $client->manager_id,
                        'status' => 'Active',
                        'created_date' => now()->toDateString(),
                    ]
                );
            }

            $stale = collect($existingAuto->get($client->id, []))
                ->filter(fn ($a) => ! in_array($a->alert_type, $candidateTypes, true))
                ->pluck('id')
                ->all();

            if (! empty($stale)) {
                ClientAlert::query()->whereIn('id', $stale)->delete();
            }
        }

        Client::query()->whereIn('id', $clientIds)->each(function (Client $client) {
            $count = (int) $client->alerts()
                ->where('resolved', false)
                ->whereIn('alert_type', self::AUTO_RENEWAL_ALERT_TYPES)
                ->count();
            $client->updateQuietly(['renewal_alert' => $count]);
        });
    }

    private function buildRenewalAlertMap(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $alerts = ClientAlert::query()
            ->whereIn('client_id', $clientIds)
            ->orderBy('expiry_date')
            ->get(['id', 'client_id', 'alert_type', 'expiry_date', 'days_remaining', 'status', 'resolved', 'created_at', 'manager_id']);

        $managerIds = $alerts->pluck('manager_id')->filter()->unique()->values()->all();
        $managers = [];
        if (! empty($managerIds)) {
            $managers = \App\Models\User::whereIn('id', $managerIds)->pluck('name', 'id')->all();
        }

        $map = [];
        foreach ($alerts as $alert) {
            $clientId = (int) $alert->client_id;
            $map[$clientId] ??= [];
            $map[$clientId][] = [
                'id' => $alert->id,
                'alert_type' => $alert->alert_type,
                'expiry_date' => $alert->expiry_date?->format('Y-m-d'),
                'display_date' => $alert->expiry_date?->format('d-M-Y'),
                'days_remaining' => $alert->days_remaining,
                'status' => $alert->status,
                'resolved' => (bool) $alert->resolved,
                'manager' => $managers[$alert->manager_id] ?? null,
                'created_date' => $alert->created_at?->format('d-M-Y'),
            ];
        }

        return $map;
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
        if ($this->hasClientColumn($sort)) {
            $query->orderBy('clients.' . $sort, $direction);
            return;
        }

        $query->orderBy('clients.submitted_at', 'desc');
    }

    private function countCacheKey(Request $request, array $validated): string
    {
        $keys = [
            'status', 'submitted_from', 'submitted_to', 'company_name',
            'account_number', 'wo_number', 'manager_id', 'team_leader_id',
            'sales_agent_id', 'alert_type',
        ];
        $filters = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $validated)) {
                $filters[$k] = $validated[$k];
            }
        }
        return 'clients_count_' . $request->user()->id . '_' . md5(json_encode($filters));
    }

    private function hasClientColumn(string $column): bool
    {
        if (self::$clientTableColumns === null) {
            self::$clientTableColumns = [];
            foreach (Schema::getColumnListing('clients') as $col) {
                self::$clientTableColumns[$col] = true;
            }
        }
        return isset(self::$clientTableColumns[$column]);
    }

    /**
     * Keep only keys that actually exist in clients table.
     * Prevents SQL errors when code is ahead of local DB migrations.
     */
    private function onlyExistingClientColumns(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($this->hasClientColumn((string) $key)) {
                $out[$key] = $value;
            }
        }
        return $out;
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
            'submission_type' => 'clients.submission_type',
            'service_category' => 'clients.service_category',
            'address' => 'clients.address',
            'product_name' => 'clients.product_name',
            'mrc' => 'clients.mrc',
            'quantity' => 'clients.quantity',
            'other' => 'clients.other',
            'migration_numbers' => 'clients.migration_numbers',
            'activity' => 'clients.activity',
            'fiber' => 'clients.fiber',
            'order_number' => 'clients.order_number',
            'wo_number' => 'clients.wo_number',
            'work_order_status' => 'clients.work_order_status',
            'activation_date' => 'clients.activation_date',
            'completion_date' => 'clients.completion_date',
            'payment_connection' => 'clients.payment_connection',
            'contract_type' => 'clients.contract_type',
            'contract_end_date' => 'clients.contract_end_date',
            'clawback_chum' => 'clients.clawback_chum',
            'remarks' => 'clients.remarks',
            'renewal_alert' => 'clients.renewal_alert',
            'additional_notes' => 'clients.additional_notes',
            'created_by' => 'clients.created_by',
            'creator' => 'clients.created_by',
        ];
        $optionalColumns = ['revenue' => 'clients.revenue', 'csr_name_1' => 'clients.csr_name_1', 'csr_name_2' => 'clients.csr_name_2', 'csr_name_3' => 'clients.csr_name_3'];
        foreach ($optionalColumns as $key => $select) {
            if ($this->hasClientColumn($key)) {
                $map[$key] = $select;
            }
        }
        foreach ($columns as $col) {
            if ($col === 'id' || $col === 'status') {
                continue;
            }
            if (isset($map[$col])) {
                $select = $map[$col];
                if (str_starts_with($select, 'clients.')) {
                    $dbCol = substr($select, 8);
                    if (! $this->hasClientColumn($dbCol)) {
                        continue;
                    }
                }
                $base[] = $select;
            }
        }
        return array_unique($base);
    }

    private function formatRow(Client $row, array $columns, array $renewalAlertMap = []): array
    {
        $needsManager = in_array('manager', $columns, true) && ! $row->manager_id && $row->sales_agent_id;
        $needsTeamLeader = in_array('team_leader', $columns, true) && ! $row->team_leader_id && $row->sales_agent_id;

        if ($needsManager || $needsTeamLeader) {
            $agent = $row->relationLoaded('salesAgent') ? $row->salesAgent : null;
            if ($agent) {
                if ($needsTeamLeader && $agent->team_leader_id) {
                    $row->team_leader_id = $agent->team_leader_id;
                }
                if ($needsManager && $agent->manager_id) {
                    $row->manager_id = $agent->manager_id;
                }
            }
        }

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
                $out[$col] = $row->$col ? $row->$col->format('d-M-Y H:i') : null;
                continue;
            }
            if (in_array($col, ['activation_date', 'completion_date', 'contract_end_date'], true)) {
                if ($col === 'activation_date') {
                    $activation = $row->activation_date;
                    if (! $activation && $row->submitted_at) {
                        $activation = $row->submitted_at;
                    }
                    $out[$col] = $activation ? $activation->format('d/m/Y') : null;
                    continue;
                }
                $out[$col] = $row->$col ? $row->$col->format('d/m/Y') : null;
                continue;
            }
            if ($col === 'activity') {
                $out[$col] = $row->activity ?: ($row->other ?: null);
                continue;
            }
            if ($col === 'remarks') {
                $out[$col] = $row->remarks ?: ($row->additional_notes ?: null);
                continue;
            }
            if ($col === 'work_order_status') {
                $out[$col] = $row->work_order_status ?: ($row->status ?: null);
                continue;
            }
            if ($col === 'full_address') {
                $addr = $row->addresses?->first();
                $out[$col] = $addr?->full_address ?: collect([$addr?->unit, $addr?->building, $addr?->area, $addr?->emirates])->filter()->implode(', ');
                continue;
            }
            if (in_array($col, [
                'trade_license_issuing_authority', 'company_category', 'trade_license_number',
                'establishment_card_number', 'account_taken_from', 'account_transfer_given_to',
                'account_manager_name', 'first_bill', 'second_bill', 'third_bill', 'fourth_bill',
                'additional_comment_1', 'additional_comment_2',
            ], true)) {
                $out[$col] = $row->companyDetail?->$col;
                continue;
            }
            if (in_array($col, ['trade_license_expiry_date', 'establishment_card_expiry_date', 'account_mapping_date', 'account_transfer_given_date'], true)) {
                $out[$col] = $row->companyDetail?->$col?->format('d/m/Y');
                continue;
            }
            if ($col === 'renewal_alert') {
                $details = $renewalAlertMap[(int) $row->id] ?? [];
                $out['renewal_alert'] = count($details);
                $out['renewal_alert_details'] = $details;
                continue;
            }
            $out[$col] = $row->$col ?? null;
        }
        return $out;
    }

    /**
     * GET /api/clients/filters
     *
     * Response shape: { success: bool, data: FilterBuckets, message: string, meta: {} }
     */
    public function filters(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);
        $this->enableLocalQueryProfile();
        $filters = app(FilterRepository::class)->forClients($request->query(), $request->user());
        $resource = (new FilterResource($filters))->resolve();
        $response = response()->json([
            'success' => true,
            'data' => $resource,
            'message' => 'Filters fetched successfully.',
            'meta' => [],
        ], 200);

        $response = $this->withCacheHeaders($response, 'clients:filters:last-modified');
        $this->logLocalSlowQueries($request->path());

        return $response;
    }

    /**
     * Optional combined payload endpoint; enabled with AGGREGATE_CF=true.
     */
    public function clientsAndFilters(Request $request): JsonResponse
    {
        abort_unless((bool) env('AGGREGATE_CF', false), 404);
        $this->authorize('viewAny', Client::class);
        $this->enableLocalQueryProfile();

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'string'],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string'],
        ]);
        $columns = $this->resolveColumns($request->user(), $validated['columns'] ?? null, $this->resolvePrefModule($request));
        $paginator = app(ClientRepository::class)->list($validated, $columns);
        $clients = ClientResource::collection(collect($paginator->items()))->resolve();
        $filters = (new FilterResource(app(FilterRepository::class)->forClients($request->query(), $request->user())))->resolve();

        $response = response()->json([
            'success' => true,
            'data' => [
                'clients' => $clients,
                'filters' => $filters,
            ],
            'message' => 'Clients and filters fetched successfully.',
            'meta' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => optional($paginator->nextCursor())->encode(),
                'prev_cursor' => optional($paginator->previousCursor())->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 200);

        $this->logLocalSlowQueries($request->path());

        return $response;
    }

    private function withCacheHeaders(JsonResponse $response, string $cacheKey): JsonResponse
    {
        $lastModified = Cache::remember($cacheKey, 60, fn () => DB::table('clients')->max('updated_at'));
        if ($lastModified) {
            $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', strtotime($lastModified)) . ' GMT');
        }
        $response->headers->set('Cache-Control', 'private, max-age=300');

        return $response;
    }

    /**
     * @template T
     * @param  callable():T  $resolver
     * @return T
     */
    private function rememberByTags(string $key, int $seconds, callable $resolver): mixed
    {
        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            return Cache::tags(['clients', 'filters'])->remember($key, $seconds, $resolver);
        }

        return Cache::remember($key, $seconds, $resolver);
    }

    private function enableLocalQueryProfile(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    private function logLocalSlowQueries(string $path): void
    {
        if (! app()->isLocal()) {
            return;
        }

        foreach (DB::getQueryLog() as $entry) {
            $time = (float) ($entry['time'] ?? 0);
            if ($time <= 50) {
                continue;
            }
            \Log::warning('Slow query (>50ms)', [
                'path' => $path,
                'time_ms' => $time,
                'sql' => $entry['query'] ?? null,
            ]);
        }
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);
        $prefModule = $this->resolvePrefModule($request);

        $config = config('modules.clients.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            $allColumns[] = [
                'key' => $key,
                'label' => $def['label'] ?? $key,
            ];
        }

        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', $prefModule)
            ->first();

        $defaultColumns = config('modules.clients.default_columns', []);
        $visible = $pref?->visible_columns ?? $defaultColumns;

        return response()->json([
            'module' => $prefModule,
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
            'default_columns' => $defaultColumns,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);
        $prefModule = $this->resolvePrefModule($request);

        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']);
        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in($allAllowed)],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => $prefModule],
            ['visible_columns' => $data['visible_columns']]
        );

        Cache::forget("col_pref_{$request->user()->id}_" . $prefModule);

        return response()->json(['success' => true, 'module' => $prefModule]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return response()->json(['message' => 'Could not read file.'], 422);
        }

        $header = fgetcsv($handle);
        if ($header === false || empty($header)) {
            fclose($handle);
            return response()->json(['message' => 'CSV file is empty or invalid.'], 422);
        }

        $header = array_map('trim', $header);
        $created = 0;
        $errors = [];

        $pick = static function (array $data, array $keys, $default = null) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                    return trim((string) $data[$key]);
                }
            }

            return $default;
        };

        $toNullableDate = static function (?string $value): ?string {
            $v = trim((string) ($value ?? ''));
            if ($v === '') {
                return null;
            }
            try {
                return \Carbon\Carbon::parse($v)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $rowNumber = 1; // includes header row
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($row) !== count($header)) {
                $row = array_pad($row, count($header), '');
            }
            $data = array_combine($header, $row);
            if ($data === false) {
                continue;
            }
            $companyName = $pick($data, ['company_name', 'Company Name']);
            if ($companyName === '') {
                continue;
            }
            if (strtolower($companyName) === 'required') {
                continue; // validation guide row from sample CSV
            }

            $status = $pick($data, ['status', 'Status'], null);
            if (! in_array((string) $status, Client::STATUSES, true)) {
                $status = 'Normal';
            }

            $quantityRaw = $pick($data, ['quantity', 'Quantity'], null);
            $quantity = null;
            if ($quantityRaw !== null && $quantityRaw !== '' && is_numeric($quantityRaw)) {
                $quantity = (int) $quantityRaw;
            }

            $renewalAlertRaw = $pick($data, ['renewal_alert', 'Renewal Alert'], null);
            $renewalAlert = null;
            if ($renewalAlertRaw !== null && $renewalAlertRaw !== '' && is_numeric($renewalAlertRaw)) {
                $renewalAlert = (int) $renewalAlertRaw;
            }

            $missing = [];
            $requiredChecks = [
                'account_number' => $pick($data, ['account_number', 'Account Number'], ''),
                'company_category' => $pick($data, ['company_category', 'Company Category'], ''),
                'trade_license_number' => $pick($data, ['trade_license_number', 'Trade License Number'], ''),
                'first_bill' => $pick($data, ['first_bill', 'First Bill'], ''),
                'second_bill' => $pick($data, ['second_bill', 'Second Bill'], ''),
                'third_bill' => $pick($data, ['third_bill', 'Third Bill'], ''),
                'fourth_bill' => $pick($data, ['fourth_bill', 'Fourth Bill'], ''),
                'account_manager_name' => $pick($data, ['account_manager_name', 'Account Manager Name'], ''),
                'csr_name_1' => $pick($data, ['csr_name_1', 'CSR Name 1', 'CSR Name'], ''),
                'contact_1_name' => $pick($data, ['contact_1_name', 'Contact 1 Name', 'Contact Person Name'], ''),
                'contact_1_contact_number' => $pick($data, ['contact_1_contact_number', 'Contact 1 Contact Number', 'Contact Number'], ''),
                'contact_1_email' => $pick($data, ['contact_1_email', 'Contact 1 Email', 'Email ID'], ''),
            ];
            foreach ($requiredChecks as $k => $v) {
                if (trim((string) $v) === '') {
                    $missing[] = $k;
                }
            }
            if (! empty($missing)) {
                $errors[] = "Row {$rowNumber} skipped: missing required field(s): " . implode(', ', $missing);
                continue;
            }

            $managerId = $pick($data, ['manager_id', 'Manager ID'], null);
            $teamLeaderId = $pick($data, ['team_leader_id', 'Team Leader ID'], null);
            $salesAgentId = $pick($data, ['sales_agent_id', 'Sales Agent ID'], null);

            $accountNumber = $this->normalizeAccountNumber($pick($data, ['account_number', 'Account Number'], ''));
            try {
                $this->ensureAccountNumberIsUnique($accountNumber);
            } catch (ValidationException $e) {
                $errors[] = "Row {$rowNumber} skipped: " . ($e->errors()['account_number'][0] ?? 'Duplicate account number.');
                continue;
            }

            $payload = [
                'company_name' => $companyName,
                'account_number' => $accountNumber,
                'status' => $status,
                'submitted_at' => $toNullableDate($pick($data, ['submitted_at', 'Submission Date'])),
                'manager_id' => is_numeric((string) $managerId) ? (int) $managerId : null,
                'team_leader_id' => is_numeric((string) $teamLeaderId) ? (int) $teamLeaderId : null,
                'sales_agent_id' => is_numeric((string) $salesAgentId) ? (int) $salesAgentId : null,
                'submission_type' => $pick($data, ['submission_type', 'Submission Type']),
                'service_category' => $pick($data, ['service_category', 'Service Category']),
                'service_type' => $pick($data, ['service_type', 'Service Type']),
                'product_type' => $pick($data, ['product_type', 'Product Type']),
                'address' => $pick($data, ['address', 'Address']),
                'product_name' => $pick($data, ['product_name', 'Product Name']),
                'mrc' => $pick($data, ['mrc', 'MRC']),
                'quantity' => $quantity,
                'other' => $pick($data, ['other', 'Other', 'offer', 'Offer']),
                'migration_numbers' => $pick($data, ['migration_numbers', 'Migration Numbers']),
                'fiber' => $pick($data, ['fiber', 'Fiber']),
                'order_number' => $pick($data, ['order_number', 'Order Number']),
                'activity' => $pick($data, ['activity', 'Activity']),
                'wo_number' => $pick($data, ['wo_number', 'WO Number', 'Work Order']),
                'work_order_status' => $pick($data, ['work_order_status', 'Work Order Status']),
                'activation_date' => $toNullableDate($pick($data, ['activation_date', 'Activation Date'])),
                'completion_date' => $toNullableDate($pick($data, ['completion_date', 'Completion Date'])),
                'payment_connection' => $pick($data, ['payment_connection', 'Payment Connection']),
                'contract_type' => $pick($data, ['contract_type', 'Contract Type']),
                'contract_end_date' => $toNullableDate($pick($data, ['contract_end_date', 'Contract End Date'])),
                'clawback_chum' => $pick($data, ['clawback_chum', 'Clawback / Chum', 'Clawback/Chum']),
                'remarks' => $pick($data, ['remarks', 'Remarks']),
                'renewal_alert' => $renewalAlert,
                'additional_notes' => $pick($data, ['additional_notes', 'Additional Notes']),
                'csr_name_1' => $pick($data, ['csr_name_1', 'CSR Name 1', 'CSR Name']),
                'csr_name_2' => $pick($data, ['csr_name_2', 'CSR Name 2']),
                'csr_name_3' => $pick($data, ['csr_name_3', 'CSR Name 3']),
            ];
            $payload['created_by'] = $request->user()->id;
            try {
                DB::transaction(function () use ($payload, $data, $pick, $toNullableDate) {
                    $client = Client::create($this->onlyExistingClientColumns($payload));

                    $companyDetail = [
                        'trade_license_issuing_authority' => $pick($data, ['trade_license_issuing_authority', 'Trade License Issuing Authority']),
                        'company_category' => $pick($data, ['company_category', 'Company Category']),
                        'trade_license_number' => $pick($data, ['trade_license_number', 'Trade License Number']),
                        'trade_license_expiry_date' => $toNullableDate($pick($data, ['trade_license_expiry_date', 'Trade License Expiry Date'])),
                        'establishment_card_number' => $pick($data, ['establishment_card_number', 'Establishment Card Number']),
                        'establishment_card_expiry_date' => $toNullableDate($pick($data, ['establishment_card_expiry_date', 'Establishment Card Expiry Date'])),
                        'account_taken_from' => $pick($data, ['account_taken_from', 'Account Taken From']),
                        'account_mapping_date' => $toNullableDate($pick($data, ['account_mapping_date', 'Account Mapping Date'])),
                        'account_transfer_given_to' => $pick($data, ['account_transfer_given_to', 'Account Transfer Given To']),
                        'account_transfer_given_date' => $toNullableDate($pick($data, ['account_transfer_given_date', 'Account Transfer Given Date'])),
                        'account_manager_name' => $pick($data, ['account_manager_name', 'Account Manager Name']),
                        'csr_name_1' => $pick($data, ['csr_name_1', 'CSR Name 1', 'CSR Name']),
                        'csr_name_2' => $pick($data, ['csr_name_2', 'CSR Name 2']),
                        'csr_name_3' => $pick($data, ['csr_name_3', 'CSR Name 3']),
                        'first_bill' => $pick($data, ['first_bill', 'First Bill']),
                        'second_bill' => $pick($data, ['second_bill', 'Second Bill']),
                        'third_bill' => $pick($data, ['third_bill', 'Third Bill']),
                        'fourth_bill' => $pick($data, ['fourth_bill', 'Fourth Bill']),
                        'additional_comment_1' => $pick($data, ['additional_comment_1', 'Additional Comment 1', 'Additional Note 1']),
                        'additional_comment_2' => $pick($data, ['additional_comment_2', 'Additional Comment 2', 'Additional Note 2']),
                    ];
                    if (collect($companyDetail)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()) {
                        $companyDetail['client_id'] = $client->id;
                        ClientCompanyDetail::create($companyDetail);
                    }

                    $contact = [
                        'name' => $pick($data, ['contact_1_name', 'Contact 1 Name', 'Contact Person Name']),
                        'designation' => $pick($data, ['contact_1_designation', 'Contact 1 Designation', 'Designation']),
                        'contact_number' => $pick($data, ['contact_1_contact_number', 'Contact 1 Contact Number', 'Contact Number']),
                        'alternate_number' => $pick($data, ['contact_1_alternate_number', 'Contact 1 Alternate Number', 'Alternate Contact Number']),
                        'email' => $pick($data, ['contact_1_email', 'Contact 1 Email', 'Email ID']),
                        'as_updated_or_not' => $pick($data, ['contact_1_as_updated_or_not', 'Contact 1 AS Updated or Not']),
                        'as_expiry_date' => $toNullableDate($pick($data, ['contact_1_as_expiry_date', 'Contact 1 AS Expiry Date'])),
                        'additional_note' => $pick($data, ['contact_1_additional_note', 'Contact 1 Additional Note']),
                    ];
                    if (collect($contact)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()) {
                        $contact['client_id'] = $client->id;
                        $contact['sort_order'] = 0;
                        ClientContact::create($contact);
                    }

                    $address = [
                        'full_address' => $pick($data, ['address_1_full_address', 'Address 1 Full Address', 'Full Address']),
                        'area' => $pick($data, ['address_1_area', 'Address 1 Area', 'Area']),
                        'building' => $pick($data, ['address_1_building', 'Address 1 Building', 'Building']),
                        'unit' => $pick($data, ['address_1_unit', 'Address 1 Unit', 'Unit']),
                        'emirates' => $pick($data, ['address_1_emirates', 'Address 1 Emirates', 'Emirates']),
                    ];
                    if (collect($address)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()) {
                        $address['client_id'] = $client->id;
                        $address['sort_order'] = 0;
                        ClientAddress::create($address);
                    }
                });
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNumber} ({$companyName}) failed: " . $e->getMessage();
            }
        }
        fclose($handle);

        try {
            SystemAuditLog::record('client.bulk_imported', null, ['count' => $created], $request->user()->id, 'client');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => "Imported {$created} client(s).",
            'created' => $created,
            'errors' => array_slice($errors, 0, 10),
        ]);
    }

    /**
     * Create a new client with company detail, contacts, and addresses.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'submitted_at' => ['sometimes', 'nullable', 'date'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'account_manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'csrs' => ['sometimes', 'array', 'max:20'],
            'csrs.*.user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(Client::STATUSES)],
            'submission_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'product_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'mrc' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'other' => ['sometimes', 'nullable', 'string', 'max:500'],
            'migration_numbers' => ['sometimes', 'nullable', 'string', 'max:100'],
            'activity' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fiber' => ['sometimes', 'nullable', 'string', 'max:20'],
            'order_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'wo_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'work_order_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'activation_date' => ['sometimes', 'nullable', 'date'],
            'completion_date' => ['sometimes', 'nullable', 'date'],
            'payment_connection' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_end_date' => ['sometimes', 'nullable', 'date'],
            'clawback_chum' => ['sometimes', 'nullable', 'string', 'max:10'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
            'renewal_alert' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'additional_notes' => ['sometimes', 'nullable', 'string'],
            'csr_name_1' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_2' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_3' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_detail' => ['sometimes', 'array'],
            'company_detail.trade_license_issuing_authority' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_detail.company_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_detail.trade_license_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_detail.trade_license_expiry_date' => ['sometimes', 'nullable', 'string', 'max:20'],
            'company_detail.establishment_card_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_detail.establishment_card_expiry_date' => ['sometimes', 'nullable', 'string', 'max:20'],
            'company_detail.account_taken_from' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_detail.account_mapping_date' => ['sometimes', 'nullable', 'string', 'max:20'],
            'company_detail.account_transfer_given_to' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_detail.account_transfer_given_date' => ['sometimes', 'nullable', 'string', 'max:20'],
            'company_detail.account_manager_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_detail.csr_name_1' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_detail.csr_name_2' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_detail.csr_name_3' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_detail.first_bill' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_detail.second_bill' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_detail.third_bill' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_detail.fourth_bill' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_detail.additional_comment_1' => ['sometimes', 'nullable', 'string'],
            'company_detail.additional_comment_2' => ['sometimes', 'nullable', 'string'],
            'contacts' => ['sometimes', 'array', 'max:10'],
            'contacts.*.name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'contacts.*.designation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contacts.*.contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contacts.*.alternate_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contacts.*.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contacts.*.as_updated_or_not' => ['sometimes', 'nullable', 'string', 'max:20'],
            'contacts.*.as_expiry_date' => ['sometimes', 'nullable', 'date'],
            'contacts.*.additional_note' => ['sometimes', 'nullable', 'string'],
            'addresses' => ['sometimes', 'array', 'max:5'],
            'addresses.*.full_address' => ['sometimes', 'nullable', 'string'],
            'addresses.*.unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'addresses.*.building' => ['sometimes', 'nullable', 'string', 'max:200'],
            'addresses.*.area' => ['sometimes', 'nullable', 'string', 'max:200'],
            'addresses.*.emirates' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $accountManagerId = $validated['account_manager_id'] ?? $validated['manager_id'] ?? null;
        $normalizedAccountNumber = $this->normalizeAccountNumber($validated['account_number'] ?? null);
        $this->ensureAccountNumberIsUnique($normalizedAccountNumber);
        $clientData = [
            'company_name' => $validated['company_name'],
            'account_number' => $normalizedAccountNumber,
            'submitted_at' => isset($validated['submitted_at']) ? $validated['submitted_at'] : now(),
            'manager_id' => $validated['manager_id'] ?? $accountManagerId,
            'account_manager_id' => $accountManagerId,
            'team_leader_id' => $validated['team_leader_id'] ?? null,
            'sales_agent_id' => $validated['sales_agent_id'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'created_by' => $user->id,
        ];
        $clientFields = ['submission_type', 'service_category', 'service_type', 'product_type', 'address', 'product_name', 'mrc', 'quantity', 'other', 'migration_numbers', 'activity', 'fiber', 'order_number', 'wo_number', 'work_order_status', 'activation_date', 'completion_date', 'payment_connection', 'contract_type', 'contract_end_date', 'clawback_chum', 'remarks', 'renewal_alert', 'additional_notes', 'csr_name_1', 'csr_name_2', 'csr_name_3'];
        foreach ($clientFields as $key) {
            if (array_key_exists($key, $validated)) {
                $clientData[$key] = $validated[$key];
            }
        }

        $client = DB::transaction(function () use ($validated, $clientData, $user) {
            $client = Client::create($this->onlyExistingClientColumns($clientData));

            if (! empty($validated['company_detail'])) {
                $cd = array_merge($validated['company_detail'], ['client_id' => $client->id]);
                $cd = self::normalizeCompanyDetailDates($cd);
                ClientCompanyDetail::create($cd);
            }

            foreach ($validated['contacts'] ?? [] as $i => $row) {
                $contactData = collect($row)->filter(fn ($v) => $v !== null && $v !== '')->all();
                if (empty($contactData)) {
                    continue;
                }
                $contactData['client_id'] = $client->id;
                $contactData['sort_order'] = $i;
                ClientContact::create($contactData);
            }

            foreach ($validated['addresses'] ?? [] as $i => $row) {
                $addrData = collect($row)->filter(fn ($v) => $v !== null && $v !== '')->all();
                if (empty($addrData)) {
                    continue;
                }
                $addrData['client_id'] = $client->id;
                $addrData['sort_order'] = $i;
                ClientAddress::create($addrData);
            }

            foreach ($validated['csrs'] ?? [] as $i => $row) {
                if (! empty($row['user_id'])) {
                    ClientCsr::create([
                        'client_id' => $client->id,
                        'user_id' => $row['user_id'],
                        'sort_order' => $i,
                    ]);
                }
            }

            return $client;
        });

        $client->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'accountManager:id,name', 'csrs.user:id,name', 'companyDetail', 'contacts', 'addresses']);

        return response()->json([
            'success' => true,
            'message' => 'Client created successfully.',
            'status' => 201,
            'data' => $this->showPayload($client),
        ], 201);
    }

    /**
     * Add a new Product/Service row against an existing client account.
     */
    public function storeProduct(Request $request, Client $client): JsonResponse
    {
        $this->authorize('create', Client::class);

        if (! empty(trim((string) $client->account_number))) {
            throw ValidationException::withMessages([
                'account_number' => 'This account number is already assigned to a client. 1 account can only be linked to 1 client.',
            ]);
        }

        $validated = $request->validate([
            'submitted_at' => ['sometimes', 'nullable', 'date'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(Client::STATUSES)],
            'submission_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'product_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'mrc' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'other' => ['sometimes', 'nullable', 'string', 'max:500'],
            'migration_numbers' => ['sometimes', 'nullable', 'string', 'max:100'],
            'activity' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fiber' => ['sometimes', 'nullable', 'string', 'max:20'],
            'order_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'wo_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'work_order_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'activation_date' => ['sometimes', 'nullable', 'date'],
            'completion_date' => ['sometimes', 'nullable', 'date'],
            'payment_connection' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_end_date' => ['sometimes', 'nullable', 'date'],
            'clawback_chum' => ['sometimes', 'nullable', 'string', 'max:10'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
            'renewal_alert' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'additional_notes' => ['sometimes', 'nullable', 'string'],
            'csr_name_1' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_2' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_3' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csrs' => ['sometimes', 'array', 'max:20'],
            'csrs.*.user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $clientData = [
            // Always attach new product to the same client identity.
            'company_name' => $client->company_name,
            'account_number' => $client->account_number,
            'submitted_at' => $validated['submitted_at'] ?? now(),
            'manager_id' => $validated['manager_id'] ?? $client->manager_id,
            'account_manager_id' => $validated['manager_id'] ?? $client->account_manager_id ?? $client->manager_id,
            'team_leader_id' => $validated['team_leader_id'] ?? $client->team_leader_id,
            'sales_agent_id' => $validated['sales_agent_id'] ?? $client->sales_agent_id,
            'status' => $validated['status'] ?? ($client->status ?: 'Normal'),
            'created_by' => $user->id,
        ];

        $clientFields = [
            'submission_type', 'service_category', 'service_type', 'product_type', 'address',
            'product_name', 'mrc', 'quantity', 'other', 'migration_numbers', 'activity', 'fiber',
            'order_number', 'wo_number', 'work_order_status', 'activation_date', 'completion_date',
            'payment_connection', 'contract_type', 'contract_end_date', 'clawback_chum', 'remarks',
            'renewal_alert', 'additional_notes', 'csr_name_1', 'csr_name_2', 'csr_name_3',
        ];
        foreach ($clientFields as $key) {
            if (array_key_exists($key, $validated)) {
                $clientData[$key] = $validated[$key];
            }
        }

        $newProduct = DB::transaction(function () use ($validated, $clientData, $client) {
            $newProduct = Client::create($this->onlyExistingClientColumns($clientData));

            $incomingCsrs = $validated['csrs'] ?? [];
            if (! empty($incomingCsrs)) {
                foreach ($incomingCsrs as $i => $row) {
                    if (! empty($row['user_id'])) {
                        ClientCsr::create([
                            'client_id' => $newProduct->id,
                            'user_id' => $row['user_id'],
                            'sort_order' => $i,
                        ]);
                    }
                }
            } else {
                $parentCsrs = ClientCsr::query()
                    ->where('client_id', $client->id)
                    ->orderBy('sort_order')
                    ->get(['user_id', 'sort_order']);
                foreach ($parentCsrs as $csr) {
                    ClientCsr::create([
                        'client_id' => $newProduct->id,
                        'user_id' => $csr->user_id,
                        'sort_order' => $csr->sort_order,
                    ]);
                }
            }

            return $newProduct;
        });

        $newProduct->load(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'accountManager:id,name', 'csrs.user:id,name', 'companyDetail', 'contacts', 'addresses']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'status' => 201,
            'data' => $this->showPayload($newProduct),
        ], 201);
    }

    /**
     * Normalize company_detail date fields: empty string to null, parse to Y-m-d for DB.
     */
    private static function normalizeCompanyDetailDates(array $cd): array
    {
        $dateKeys = [
            'trade_license_expiry_date',
            'establishment_card_expiry_date',
            'account_mapping_date',
            'account_transfer_given_date',
        ];
        foreach ($dateKeys as $key) {
            $v = $cd[$key] ?? null;
            if ($v === null || $v === '') {
                $cd[$key] = null;
                continue;
            }
            $str = trim((string) $v);
            if ($str === '') {
                $cd[$key] = null;
                continue;
            }
            try {
                $parsed = Carbon::parse($str);
                $cd[$key] = $parsed->format('Y-m-d');
            } catch (\Throwable) {
                $cd[$key] = null;
            }
        }
        return $cd;
    }

    private function showPayload(Client $client): array
    {
        if ($client->sales_agent_id) {
            $agent = $client->salesAgent;
            if ($agent) {
                $lookupIds = [];
                if (! $client->team_leader_id && $agent->team_leader_id) {
                    $client->team_leader_id = $agent->team_leader_id;
                    $lookupIds[$agent->team_leader_id] = 'teamLeader';
                }
                if (! $client->manager_id && $agent->manager_id) {
                    $client->manager_id = $agent->manager_id;
                    $lookupIds[$agent->manager_id] = 'manager';
                }
                if (! empty($lookupIds)) {
                    $users = User::whereIn('id', array_keys($lookupIds))->get()->keyBy('id');
                    foreach ($lookupIds as $id => $relation) {
                        $client->setRelation($relation, $users->get($id));
                    }
                }
            }
        }

        $companyDetail = $client->companyDetail;
        return [
            'id' => $client->id,
            'company_name' => $client->company_name,
            'account_number' => $client->account_number,
            'submitted_at' => $client->submitted_at?->toIso8601String(),
            'manager_id' => $client->manager_id,
            'manager' => $client->manager?->name,
            'account_manager_id' => $client->account_manager_id,
            'account_manager' => $client->accountManager?->name,
            'team_leader_id' => $client->team_leader_id,
            'team_leader' => $client->teamLeader?->name,
            'sales_agent_id' => $client->sales_agent_id,
            'sales_agent' => $client->salesAgent?->name,
            'csrs' => $client->relationLoaded('csrs') ? $client->csrs->map(fn ($c) => ['user_id' => $c->user_id, 'user' => $c->user?->name])->values()->all() : [],
            'status' => $client->status,
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
    }

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $client->load([
            'manager:id,name',
            'accountManager:id,name',
            'teamLeader:id,name',
            'salesAgent:id,name',
            'creator:id,name',
            'companyDetail',
            'contacts',
            'addresses',
            'csrs.user:id,name',
        ]);

        $companyDetail = $client->companyDetail;
        $base = [
            'id' => $client->id,
            'company_name' => $client->company_name,
            'account_number' => $client->account_number,
            'submitted_at' => $client->submitted_at?->toIso8601String(),
            'manager_id' => $client->manager_id,
            'manager' => $client->manager?->name,
            'account_manager_id' => $client->account_manager_id,
            'account_manager' => $client->accountManager?->name,
            'csrs' => $client->csrs->map(fn ($c) => ['user_id' => $c->user_id, 'user' => $c->user?->name])->values()->all(),
            'team_leader_id' => $client->team_leader_id,
            'team_leader' => $client->teamLeader?->name,
            'sales_agent_id' => $client->sales_agent_id,
            'sales_agent' => $client->salesAgent?->name,
            'status' => $client->status,
            'submission_type' => $client->submission_type,
            'service_category' => $client->service_category,
            'service_type' => $client->service_type,
            'product_type' => $client->product_type,
            'address' => $client->address,
            'product_name' => $client->product_name,
            'mrc' => $client->mrc,
            'quantity' => $client->quantity,
            'other' => $client->other,
            'migration_numbers' => $client->migration_numbers,
            'activity' => $client->activity,
            'fiber' => $client->fiber,
            'order_number' => $client->order_number,
            'wo_number' => $client->wo_number,
            'work_order_status' => $client->work_order_status,
            'activation_date' => $client->activation_date?->format('Y-m-d'),
            'completion_date' => $client->completion_date?->format('Y-m-d'),
            'payment_connection' => $client->payment_connection,
            'contract_type' => $client->contract_type,
            'contract_end_date' => $client->contract_end_date?->format('Y-m-d'),
            'clawback_chum' => $client->clawback_chum,
            'remarks' => $client->remarks,
            'renewal_alert' => $client->renewal_alert,
            'additional_notes' => $client->additional_notes,
            'created_by' => $client->created_by,
            'creator' => $client->creator?->name,
            'revenue' => $client->revenue ? (float) $client->revenue : null,
            ...$this->revenueByStatus($client),
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
     * Products & Services: return all entries across all clients.
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

        $query = Client::query();

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'submitted_at';
        $order = strtolower($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->with([
            'manager:id,name',
            'teamLeader:id,name',
            'salesAgent:id,name',
            'creator:id,name,email',
        ]);
        $query->orderBy($sort, $order);

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);
        $columns = array_merge(self::BASE_COLUMNS, self::ALLOWED_COLUMNS, ['manager', 'team_leader', 'sales_agent', 'creator']);
        $rows = $paginated->getCollection();
        $clientIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();
        $this->syncRenewalAlertsForClientIds($clientIds);
        $renewalAlertMap = $this->buildRenewalAlertMap($clientIds);

        $items = $rows->map(function ($row) use ($columns, $renewalAlertMap) {
            return $this->formatRow($row, $columns, $renewalAlertMap);
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

        $accountNumber = trim((string) $client->account_number);
        $query = \App\Models\VasRequestSubmission::query()
            ->visibleTo($request->user())
            ->where(function ($q) use ($client, $accountNumber) {
                $q->where('client_id', $client->id);
                if ($accountNumber !== '') {
                    $q->orWhere('account_number', $accountNumber);
                }
            });

        $query->with(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'creator:id,name', 'backOfficeExecutive:id,name']);
        $query->orderByDesc('submitted_at');

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginated->getCollection()->map(function ($row) {
            return [
                'id' => $row->id,
                'created_at' => $row->created_at?->format('d-M-Y H:i'),
                'request_type' => $row->request_type,
                'account_number' => $row->account_number,
                'company_name' => $row->company_name,
                'contact_number' => $row->contact_number,
                'description' => $row->description,
                'additional_notes' => $row->additional_notes,
                'manager' => $row->manager?->name,
                'team_leader' => $row->teamLeader?->name,
                'sales_agent' => $row->salesAgent?->name,
                'executive' => $row->backOfficeExecutive?->name,
                'status' => $row->status,
                'creator' => $row->creator?->name,
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

    public function customerSupport(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $accountNumber = trim((string) $client->account_number);
        $query = \App\Models\CustomerSupportSubmission::query()
            ->visibleTo($request->user())
            ->where(function ($q) use ($client, $accountNumber) {
                $q->where('client_id', $client->id);
                if ($accountNumber !== '') {
                    $q->orWhere('account_number', $accountNumber);
                }
            });

        $query->with(['manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'creator:id,name', 'csrs.user:id,name']);
        $query->orderByDesc('submitted_at');

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginated->getCollection()->map(function ($row) {
            return [
                'id' => $row->id,
                'ticket_number' => $row->ticket_number,
                'submitted_at' => $row->submitted_at?->format('d-M-Y H:i'),
                'issue_category' => $row->issue_category,
                'status' => $row->status,
                'company_name' => $row->company_name,
                'account_number' => $row->account_number,
                'contact_number' => $row->contact_number,
                'issue_description' => $row->issue_description,
                'creator' => $row->creator?->name,
                'csr' => $row->csrs->map(fn ($c) => $c->user?->name)->filter()->implode(', '),
                'manager' => $row->manager?->name,
                'team_leader' => $row->teamLeader?->name,
                'sales_agent' => $row->salesAgent?->name,
                'workflow_status' => $row->workflow_status,
                'pending' => $row->pending,
                'completion_date' => $row->completion_date?->format('d-M-Y'),
                'updated_at' => $row->updated_at?->format('d-M-Y H:i'),
                'trouble_ticket' => $row->trouble_ticket,
                'activity' => $row->activity,
                'resolution_remarks' => $row->resolution_remarks,
                'internal_remarks' => $row->internal_remarks,
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
            'changed_by' => $a->changed_by,
            'changed_by_name' => $a->changedByUser?->name ?? '—',
        ]);

        $items = $this->resolveAuditDisplayValues($items);

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
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'account_manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'csr_name_1' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_2' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csr_name_3' => ['sometimes', 'nullable', 'string', 'max:100'],
            'csrs' => ['sometimes', 'array', 'max:20'],
            'csrs.*.user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $updateData = collect($validated)->except('csrs')->all();
        if (array_key_exists('account_number', $validated)) {
            $normalizedAccountNumber = $this->normalizeAccountNumber($validated['account_number']);
            $this->ensureAccountNumberIsUnique($normalizedAccountNumber, (int) $client->id);
            $updateData['account_number'] = $normalizedAccountNumber;
        }
        if (array_key_exists('account_manager_id', $validated)) {
            $updateData['account_manager_id'] = $validated['account_manager_id'];
            $updateData['manager_id'] = $validated['manager_id'] ?? $validated['account_manager_id'];
        }

        $client->update($this->onlyExistingClientColumns($updateData));

        if (array_key_exists('csrs', $validated)) {
            ClientCsr::where('client_id', $client->id)->delete();
            foreach ($validated['csrs'] as $i => $row) {
                if (! empty($row['user_id'])) {
                    ClientCsr::create([
                        'client_id' => $client->id,
                        'user_id' => $row['user_id'],
                        'sort_order' => $i,
                    ]);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * PUT /api/clients/{client}/inline
     * Single-field inline edit from the listing table. Audit tracked via ClientObserver.
     */
    public function inlineUpdate(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'company_name'       => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'             => ['sometimes', 'nullable', 'string', Rule::in(Client::STATUSES)],
            'service_type'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'product_type'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'submission_type'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_category'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'address'            => ['sometimes', 'nullable', 'string', 'max:500'],
            'product_name'       => ['sometimes', 'nullable', 'string', 'max:200'],
            'mrc'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'quantity'           => ['sometimes', 'nullable', 'integer', 'min:0'],
            'other'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'migration_numbers'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'activity'           => ['sometimes', 'nullable', 'string', 'max:500'],
            'wo_number'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'work_order_status'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'activation_date'    => ['sometimes', 'nullable', 'date'],
            'completion_date'    => ['sometimes', 'nullable', 'date'],
            'payment_connection' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_type'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_end_date'  => ['sometimes', 'nullable', 'date'],
            'clawback_chum'      => ['sometimes', 'nullable', 'string', 'max:10'],
            'remarks'            => ['sometimes', 'nullable', 'string', 'max:500'],
            'renewal_alert'      => ['sometimes', 'nullable', 'integer', 'min:0'],
            'additional_notes'   => ['sometimes', 'nullable', 'string'],
            'manager_id'         => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id'     => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id'     => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'create_renewal_record' => ['sometimes', 'boolean'],
        ]);

        $isRenewal = ! empty($data['create_renewal_record'])
            && array_key_exists('service_category', $data)
            && strcasecmp(trim((string) ($data['service_category'] ?? '')), 'Renewal') === 0;

        if ($isRenewal) {
            $newClient = DB::transaction(function () use ($client, $request, $data) {
                $cloneData = $client->only((new Client())->getFillable());
                unset($cloneData['renewal_alert']);

                foreach ($data as $key => $value) {
                    if ($key === 'create_renewal_record') {
                        continue;
                    }
                    $cloneData[$key] = $value;
                }

                $cloneData['submitted_at'] = now();
                $cloneData['created_by'] = $request->user()->id;
                $cloneData['renewal_alert'] = 0;

                $newClient = Client::create($this->onlyExistingClientColumns($cloneData));

                $companyDetail = ClientCompanyDetail::query()->where('client_id', $client->id)->first();
                if ($companyDetail) {
                    $detailData = $companyDetail->toArray();
                    unset($detailData['id'], $detailData['client_id'], $detailData['created_at'], $detailData['updated_at']);
                    $detailData['client_id'] = $newClient->id;
                    ClientCompanyDetail::create($detailData);
                }

                $csrs = ClientCsr::query()
                    ->where('client_id', $client->id)
                    ->orderBy('sort_order')
                    ->get(['user_id', 'sort_order']);

                foreach ($csrs as $csr) {
                    ClientCsr::create([
                        'client_id' => $newClient->id,
                        'user_id' => $csr->user_id,
                        'sort_order' => $csr->sort_order,
                    ]);
                }

                return $newClient;
            });

            return response()->json([
                'id' => $newClient->id,
                'message' => 'Renewal record created.',
                'created_new_record' => true,
            ]);
        }

        $updateData = collect($data)->except('create_renewal_record')->all();
        if (array_key_exists('account_number', $data)) {
            $normalizedAccountNumber = $this->normalizeAccountNumber($data['account_number']);
            $this->ensureAccountNumberIsUnique($normalizedAccountNumber, (int) $client->id);
            $updateData['account_number'] = $normalizedAccountNumber;
        }
        $client->update($this->onlyExistingClientColumns($updateData));

        return response()->json(['id' => $client->id, 'message' => 'Updated.']);
    }

    public function updateCompanyDetails(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'trade_license_issuing_authority' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trade_license_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trade_license_expiry_date' => ['sometimes', 'nullable', 'string', 'max:20'],
            'establishment_card_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'establishment_card_expiry_date' => ['sometimes', 'nullable', 'string', 'max:20'],
            'account_taken_from' => ['sometimes', 'nullable', 'string', 'max:100'],
            'account_mapping_date' => ['sometimes', 'nullable', 'string', 'max:20'],
            'account_transfer_given_to' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_transfer_given_date' => ['sometimes', 'nullable', 'string', 'max:20'],
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

        $old = $client->companyDetail?->toArray() ?? null;
        $data = self::normalizeCompanyDetailDates(array_merge($validated, ['client_id' => $client->id]));
        ClientCompanyDetail::updateOrCreate(
            ['client_id' => $client->id],
            $data
        );
        $new = $client->fresh()->companyDetail?->toArray() ?? null;

        try {
            SystemAuditLog::record('client.company_details_updated', $old, $new, $request->user()->id, 'client', $client->id);
        } catch (\Throwable $e) {
            report($e);
        }

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

        try {
            SystemAuditLog::record('client.contacts_updated', null, ['client_id' => $client->id, 'contacts_count' => count($validated['contacts'] ?? [])], $request->user()->id, 'client', $client->id);
        } catch (\Throwable $e) {
            report($e);
        }

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

        try {
            SystemAuditLog::record('client.addresses_updated', null, ['client_id' => $client->id, 'addresses_count' => count($validated['addresses'] ?? [])], $request->user()->id, 'client', $client->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Alerts for this client.
     */
    public function alerts(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        // Ensure date-driven renewal alerts are up to date before listing.
        // This covers Trade License Expiry and Establishment Card Expiry when they fall in next month.
        $this->syncRenewalAlertsForClientIds([(int) $client->id]);

        $query = $client->alerts()->with('manager:id,name');

        if ($request->filled('alert_type')) {
            $query->where('alert_type', $request->input('alert_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('resolved')) {
            $query->where('resolved', $request->boolean('resolved'));
        }
        if ($request->filled('manager_id')) {
            $query->where('manager_id', $request->input('manager_id'));
        }
        if ($request->filled('expiry_from')) {
            $query->whereDate('expiry_date', '>=', $request->input('expiry_from'));
        }
        if ($request->filled('expiry_to')) {
            $query->whereDate('expiry_date', '<=', $request->input('expiry_to'));
        }
        if ($request->filled('created_from')) {
            $query->whereDate('created_date', '>=', $request->input('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_date', '<=', $request->input('created_to'));
        }
        if ($request->filled('days_remaining_from')) {
            $query->where('days_remaining', '>=', (int) $request->input('days_remaining_from'));
        }
        if ($request->filled('days_remaining_to')) {
            $query->where('days_remaining', '<=', (int) $request->input('days_remaining_to'));
        }

        $sortable = ['alert_type', 'company_name', 'account_number', 'expiry_date', 'days_remaining', 'status', 'created_date', 'resolved'];
        $sort = in_array($request->input('sort'), $sortable) ? $request->input('sort') : 'expiry_date';
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $order);

        $perPage = (int) $request->input('per_page', 25);
        $paginated = $query->paginate($perPage);

        $items = $paginated->getCollection()->map(fn ($a) => [
            'id' => $a->id,
            'alert_type' => $a->alert_type,
            'company_name' => $a->company_name,
            'account_number' => $a->account_number,
            'expiry_date' => $a->expiry_date?->format('d-M-Y'),
            '_raw_expiry' => $a->expiry_date?->format('Y-m-d'),
            'days_remaining' => $a->days_remaining,
            'manager' => $a->manager?->name,
            'manager_id' => $a->manager_id,
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

    public function storeAlert(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'alert_type' => ['required', 'string', 'max:80'],
            'expiry_date' => ['required', 'date'],
            'days_remaining' => ['nullable', 'integer', 'min:0'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $alert = $client->alerts()->create([
            'alert_type' => $validated['alert_type'],
            'company_name' => $client->company_name,
            'account_number' => $client->account_number,
            'expiry_date' => $validated['expiry_date'],
            'days_remaining' => $validated['days_remaining'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'status' => $validated['status'],
            'created_date' => now()->toDateString(),
            'created_by' => $request->user()->id,
        ]);

        $alert->load('manager:id,name');

        return response()->json([
            'message' => 'Alert created successfully.',
            'alert' => [
                'id' => $alert->id,
                'alert_type' => $alert->alert_type,
                'company_name' => $alert->company_name,
                'account_number' => $alert->account_number,
                'expiry_date' => $alert->expiry_date?->format('d-M-Y'),
                '_raw_expiry' => $alert->expiry_date?->format('Y-m-d'),
                'days_remaining' => $alert->days_remaining,
                'manager' => $alert->manager?->name,
                'manager_id' => $alert->manager_id,
                'status' => $alert->status,
                'created_date' => $alert->created_date?->format('d-M-Y'),
                'resolved' => $alert->resolved,
            ],
        ], 201);
    }

    public function updateAlert(Request $request, Client $client, ClientAlert $alert): JsonResponse
    {
        $this->authorize('update', $client);

        if ((int) $alert->client_id !== (int) $client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'alert_type'     => ['sometimes', 'string', 'max:80'],
            'expiry_date'    => ['sometimes', 'date'],
            'days_remaining' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'manager_id'     => ['sometimes', 'nullable', 'exists:users,id'],
            'status'         => ['sometimes', 'string', 'max:50'],
        ]);

        $alert->update($validated);
        $alert->load('manager:id,name');

        return response()->json([
            'message' => 'Alert updated.',
            'alert' => [
                'id'             => $alert->id,
                'alert_type'     => $alert->alert_type,
                'company_name'   => $alert->company_name,
                'account_number' => $alert->account_number,
                'expiry_date'    => $alert->expiry_date?->format('d-M-Y'),
                '_raw_expiry'    => $alert->expiry_date?->format('Y-m-d'),
                'days_remaining' => $alert->days_remaining,
                'manager'        => $alert->manager?->name,
                'manager_id'     => $alert->manager_id,
                'status'         => $alert->status,
                'created_date'   => $alert->created_date?->format('d-M-Y'),
                'resolved'       => $alert->resolved,
            ],
        ]);
    }

    public function resolveAlert(Request $request, Client $client, ClientAlert $alert): JsonResponse
    {
        $this->authorize('update', $client);

        if ((int) $alert->client_id !== (int) $client->id) {
            abort(404);
        }

        $alert->update([
            'resolved' => true,
            'resolved_at' => now(),
        ]);

        return response()->json(['message' => 'Alert resolved successfully.']);
    }

    public function generateRenewalAlerts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Client::STATUSES)],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'wo_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $query = Client::query()->select('clients.id');
        $this->applyFilters($query, $validated);
        $ids = $query->pluck('clients.id')->map(fn ($id) => (int) $id)->filter()->values()->all();
        $this->syncRenewalAlertsForClientIds($ids);

        return response()->json([
            'message' => 'Renewal alerts generated successfully.',
            'processed_clients' => count($ids),
        ]);
    }

    public function bulkAssignCsr(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:clients,id'],
            'csr_name' => ['required', 'string', 'max:100'],
        ]);

        Client::whereIn('id', $validated['ids'])->update(['csr_name_1' => $validated['csr_name']]);
        $now = now();
        $upsertRows = collect($validated['ids'])->map(function ($id) use ($validated, $now) {
            return [
                'client_id' => $id,
                'csr_name_1' => $validated['csr_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();
        if (! empty($upsertRows)) {
            ClientCompanyDetail::upsert($upsertRows, ['client_id'], ['csr_name_1', 'updated_at']);
        }

        Cache::flush();

        return response()->json([
            'message' => count($validated['ids']) . ' client(s) updated with CSR: ' . $validated['csr_name'] . '.',
        ]);
    }

    public function bulkAssignAccountManager(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:clients,id'],
            'account_manager_name' => ['required', 'string', 'max:200'],
        ]);

        $now = now();
        $upsertRows = collect($validated['ids'])->map(function ($id) use ($validated, $now) {
            return [
                'client_id' => $id,
                'account_manager_name' => $validated['account_manager_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();
        if (! empty($upsertRows)) {
            ClientCompanyDetail::upsert($upsertRows, ['client_id'], ['account_manager_name', 'updated_at']);
        }

        Cache::flush();

        return response()->json([
            'message' => count($validated['ids']) . ' client(s) updated with Account Manager: ' . $validated['account_manager_name'] . '.',
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('delete', new Client());

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:clients,id'],
        ]);

        $clients = Client::whereIn('id', $validated['ids'])->get(['id', 'account_number']);
        $deletedIds = $clients->pluck('id')->toArray();

        Client::whereIn('id', $deletedIds)->delete();

        // Log each deletion
        foreach ($clients as $client) {
            SystemAuditLog::record('client.deleted', [
                'client_id'      => $client->id,
                'account_number' => $client->account_number ?? null,
            ], null, $request->user()->id, 'client', $client->id);
        }

        // Only flush client-related cache tags, not entire store
        try {
            Cache::tags(['clients'])->flush();
        } catch (\Throwable $e) {
            // Driver doesn't support tags — flush module keys only
            Cache::flush();
        }

        return response()->json([
            'message' => count($deletedIds) . ' client(s) deleted successfully.',
        ]);
    }
}
