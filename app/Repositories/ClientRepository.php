<?php

namespace App\Repositories;

use App\Models\User;
use App\Services\TeamHierarchyService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClientRepository
{
    /**
     * Optimized clients listing query used by All Clients page.
     *
     * @param  array<string,mixed>  $params
     * @param  array<int,string>  $columns
     * @param  User|null  $user  When provided, applies role-based visibility
     */
    public function list(array $params, array $columns = [], ?User $user = null): LengthAwarePaginator
    {
        $this->applyQueryTimeoutGuard();

        $perPage = (int) ($params['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 50));
        $page = max(1, (int) ($params['page'] ?? 1));
        $sort = (string) ($params['sort'] ?? 'submitted_at');
        $order = strtolower((string) ($params['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('clients as c')
            ->leftJoin('users as m', 'm.id', '=', 'c.manager_id')
            ->leftJoin('users as tl', 'tl.id', '=', 'c.team_leader_id')
            ->leftJoin('users as sa', 'sa.id', '=', 'c.sales_agent_id')
            ->leftJoin('users as cr', 'cr.id', '=', 'c.created_by')
            ->leftJoin('client_company_details as cd', 'cd.client_id', '=', 'c.id')
            ->leftJoinSub($this->addressDisplaySubquery(), 'ca_addr', function ($join) {
                $join->on('ca_addr.client_id', '=', 'c.id');
            })
            ->leftJoinSub($this->primaryContactDisplaySubquery(), 'cc_primary', function ($join) {
                $join->on('cc_primary.client_id', '=', 'c.id');
            });

        // Apply user-based visibility scope
        if ($user) {
            $this->applyUserVisibility($query, $user);
        }

        $selects = $this->resolveSelectColumns($columns);
        $query->select($selects);

        $this->applyFilters($query, $params);

        [$sortColumn, $sortNeedsRaw] = $this->resolveSortColumn($sort);
        if ($sortNeedsRaw) {
            $query->orderByRaw("{$sortColumn} {$order}");
        } else {
            $query->orderBy($sortColumn, $order);
        }
        // Stable secondary sort for deterministic pagination.
        if ($sortColumn !== 'c.id') {
            $query->orderBy('c.id', 'desc');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<int,string>  $columns
     * @return array<int,string>
     */
    private function resolveSelectColumns(array $columns): array
    {
        $default = [
            'c.id',
            'c.id as _row_id',
            'c.activity',
            'c.company_name',
            'c.account_number',
            'c.submission_type',
            'c.wo_number',
            'c.status',
            'c.submitted_at',
            'c.activation_date',
            'c.completion_date',
            'c.contract_end_date',
            'c.service_category',
            'c.service_type',
            'c.product_type',
            $this->addressSelectExpression(),
            $this->fullAddressSelectExpression(),
            'c.product_name',
            'c.work_order_status',
            'c.payment_connection',
            'c.contract_type',
            'c.clawback_chum',
            'c.remarks',
            'c.additional_notes',
            'c.mrc',
            'c.quantity',
            'c.other',
            'c.migration_numbers',
            'c.created_at',
            'm.name as manager',
            'tl.name as team_leader',
            'sa.name as sales_agent',
            'cr.name as creator',
            'cd.trade_license_issuing_authority',
            'cd.company_category',
            'cd.trade_license_number',
            'cd.trade_license_expiry_date',
            'cd.establishment_card_number',
            'cd.establishment_card_expiry_date',
            'cd.account_taken_from',
            'cd.account_mapping_date',
            'cd.account_transfer_given_to',
            'cd.account_transfer_given_date',
            $this->primaryContactSelectExpression(),
            'cd.account_manager_name',
            'c.csr_name_1',
            $this->additionalComment1SelectExpression(),
            $this->additionalComment2SelectExpression(),
        ];

        if (empty($columns)) {
            return $default;
        }

        $map = [
            'id' => 'c.id',
            'activity' => 'c.activity',
            'company_name' => 'c.company_name',
            'account_number' => 'c.account_number',
            'submission_type' => 'c.submission_type',
            'wo_number' => 'c.wo_number',
            'status' => 'c.status',
            'submitted_at' => 'c.submitted_at',
            'activation_date' => 'c.activation_date',
            'completion_date' => 'c.completion_date',
            'contract_end_date' => 'c.contract_end_date',
            'service_category' => 'c.service_category',
            'service_type' => 'c.service_type',
            'product_type' => 'c.product_type',
            'address' => $this->addressSelectExpression(),
            'full_address' => $this->fullAddressSelectExpression(),
            'product_name' => 'c.product_name',
            'work_order_status' => 'c.work_order_status',
            'payment_connection' => 'c.payment_connection',
            'contract_type' => 'c.contract_type',
            'clawback_chum' => 'c.clawback_chum',
            'remarks' => 'c.remarks',
            'additional_notes' => 'c.additional_notes',
            'mrc' => 'c.mrc',
            'quantity' => 'c.quantity',
            'other' => 'c.other',
            'migration_numbers' => 'c.migration_numbers',
            'manager' => 'm.name as manager',
            'team_leader' => 'tl.name as team_leader',
            'sales_agent' => 'sa.name as sales_agent',
            'creator' => 'cr.name as creator',
            'trade_license_issuing_authority' => 'cd.trade_license_issuing_authority',
            'company_category' => 'cd.company_category',
            'trade_license_number' => 'cd.trade_license_number',
            'trade_license_expiry_date' => 'cd.trade_license_expiry_date',
            'establishment_card_number' => 'cd.establishment_card_number',
            'establishment_card_expiry_date' => 'cd.establishment_card_expiry_date',
            'account_taken_from' => 'cd.account_taken_from',
            'account_mapping_date' => 'cd.account_mapping_date',
            'account_transfer_given_to' => 'cd.account_transfer_given_to',
            'account_transfer_given_date' => 'cd.account_transfer_given_date',
            'primary_contact_detail' => $this->primaryContactSelectExpression(),
            'account_manager_name' => 'cd.account_manager_name',
            'csr_name_1' => 'c.csr_name_1',
            'csr_name_2' => 'c.csr_name_2',
            'csr_name_3' => 'c.csr_name_3',
            'first_bill' => 'cd.first_bill',
            'second_bill' => 'cd.second_bill',
            'third_bill' => 'cd.third_bill',
            'fourth_bill' => 'cd.fourth_bill',
            'additional_comment_1' => $this->additionalComment1SelectExpression(),
            'additional_comment_2' => $this->additionalComment2SelectExpression(),
            'created_at' => 'c.created_at',
        ];

        $resolved = ['c.id', 'c.id as _row_id'];
        foreach ($columns as $column) {
            if (isset($map[$column])) {
                $resolved[] = $map[$column];
            }
        }

        return $this->uniqueSelectColumns($resolved);
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private function applyFilters($query, array $params): void
    {
        $prefixLike = static function ($value): string {
            return addcslashes((string) $value, '%_\\') . '%';
        };
        $containsLike = static function ($value): string {
            return '%' . addcslashes((string) $value, '%_\\') . '%';
        };
        $dayStart = static fn (string $date): string => $date . ' 00:00:00';
        $dayEnd = static fn (string $date): string => $date . ' 23:59:59';

        $query->when(! empty($params['status']), fn ($q) => $q->where('c.status', (string) $params['status']))
            ->when(! empty($params['activity']), fn ($q) => $q->where('c.activity', 'like', $containsLike($params['activity'])))
            ->when(! empty($params['company_name']), fn ($q) => $q->where('c.company_name', 'like', $prefixLike($params['company_name'])))
            ->when(! empty($params['account_number']), fn ($q) => $q->where('c.account_number', 'like', $prefixLike($params['account_number'])))
            ->when(! empty($params['wo_number']), fn ($q) => $q->where('c.wo_number', 'like', $containsLike($params['wo_number'])))
            ->when(! empty($params['manager_id']), fn ($q) => $q->where('c.manager_id', (int) $params['manager_id']))
            ->when(! empty($params['team_leader_id']), fn ($q) => $q->where('c.team_leader_id', (int) $params['team_leader_id']))
            ->when(! empty($params['sales_agent_id']), fn ($q) => $q->where('c.sales_agent_id', (int) $params['sales_agent_id']))
            ->when(! empty($params['submission_type']), fn ($q) => $q->where('c.submission_type', 'like', $prefixLike($params['submission_type'])))
            ->when(! empty($params['service_category']), fn ($q) => $q->where('c.service_category', 'like', $prefixLike($params['service_category'])))
            ->when(! empty($params['service_type']), fn ($q) => $q->where('c.service_type', 'like', $prefixLike($params['service_type'])))
            ->when(! empty($params['product_type']), fn ($q) => $q->where('c.product_type', 'like', $prefixLike($params['product_type'])))
            ->when(! empty($params['product_name']), fn ($q) => $q->where('c.product_name', 'like', $prefixLike($params['product_name'])))
            ->when(! empty($params['work_order_status']), function ($q) use ($params, $containsLike) {
                $term = $containsLike($params['work_order_status']);
                $q->where(function ($inner) use ($term) {
                    $inner->where('c.work_order_status', 'like', $term)
                        ->orWhere('c.status', 'like', $term);
                });
            })
            ->when(! empty($params['payment_connection']), fn ($q) => $q->where('c.payment_connection', 'like', $prefixLike($params['payment_connection'])))
            ->when(! empty($params['contract_type']), fn ($q) => $q->where('c.contract_type', 'like', $prefixLike($params['contract_type'])))
            ->when(! empty($params['submitted_from']), fn ($q) => $q->where('c.submitted_at', '>=', $dayStart((string) $params['submitted_from'])))
            ->when(! empty($params['submitted_to']), fn ($q) => $q->where('c.submitted_at', '<=', $dayEnd((string) $params['submitted_to'])))
            ->when(! empty($params['activation_from']), fn ($q) => $q->where('c.activation_date', '>=', (string) $params['activation_from']))
            ->when(! empty($params['activation_to']), fn ($q) => $q->where('c.activation_date', '<=', (string) $params['activation_to']))
            ->when(! empty($params['completion_from']), fn ($q) => $q->where('c.completion_date', '>=', (string) $params['completion_from']))
            ->when(! empty($params['completion_to']), fn ($q) => $q->where('c.completion_date', '<=', (string) $params['completion_to']))
            ->when(! empty($params['contract_end_from']), fn ($q) => $q->where('c.contract_end_date', '>=', (string) $params['contract_end_from']))
            ->when(! empty($params['contract_end_to']), fn ($q) => $q->where('c.contract_end_date', '<=', (string) $params['contract_end_to']))
            ->when(! empty($params['trade_license_expiry_from']), fn ($q) => $q->where('cd.trade_license_expiry_date', '>=', (string) $params['trade_license_expiry_from']))
            ->when(! empty($params['trade_license_expiry_to']), fn ($q) => $q->where('cd.trade_license_expiry_date', '<=', (string) $params['trade_license_expiry_to']))
            ->when(! empty($params['establishment_card_expiry_from']), fn ($q) => $q->where('cd.establishment_card_expiry_date', '>=', (string) $params['establishment_card_expiry_from']))
            ->when(! empty($params['establishment_card_expiry_to']), fn ($q) => $q->where('cd.establishment_card_expiry_date', '<=', (string) $params['establishment_card_expiry_to']))
            ->when(! empty($params['alert_type']), function ($q) use ($params) {
                $q->whereExists(function ($sub) use ($params) {
                    $sub->from('client_alerts as ca')
                        ->selectRaw('1')
                        ->whereColumn('ca.client_id', 'c.id')
                        ->where('ca.alert_type', (string) $params['alert_type']);
                });
            });
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function resolveSortColumn(string $sort): array
    {
        return match ($sort) {
            'manager' => ['m.name', false],
            'team_leader' => ['tl.name', false],
            'sales_agent' => ['sa.name', false],
            'creator' => ['cr.name', false],
            'company_category' => ['cd.company_category', false],
            'address' => ["COALESCE(NULLIF(c.address, ''), NULLIF(ca_addr.primary_address, ''), NULLIF(ca_addr.any_address, ''))", true],
            default => [in_array($sort, ['id', 'activity', 'company_name', 'account_number', 'submission_type', 'wo_number', 'status', 'submitted_at', 'activation_date', 'completion_date', 'contract_end_date', 'service_category', 'service_type', 'product_type', 'product_name', 'work_order_status', 'payment_connection', 'contract_type', 'additional_notes', 'created_at'], true) ? 'c.' . $sort : 'c.submitted_at', false],
        };
    }

    private function addressDisplaySubquery()
    {
        $composed = "TRIM(CONCAT_WS(', ', NULLIF(ca.unit, ''), NULLIF(ca.building, ''), NULLIF(ca.area, ''), NULLIF(ca.emirates, '')))";
        $bestAddress = "COALESCE(NULLIF(ca.full_address, ''), NULLIF({$composed}, ''))";

        return DB::table('client_addresses as ca')
            ->select('ca.client_id')
            ->selectRaw("MAX(CASE WHEN COALESCE(ca.sort_order, 999999) = 0 THEN {$bestAddress} END) as primary_address")
            ->selectRaw("MAX({$bestAddress}) as any_address")
            ->groupBy('ca.client_id');
    }

    private function addressSelectExpression()
    {
        return DB::raw("COALESCE(NULLIF(c.address, ''), NULLIF(ca_addr.primary_address, ''), NULLIF(ca_addr.any_address, '')) as address");
    }

    private function fullAddressSelectExpression()
    {
        return DB::raw("COALESCE(NULLIF(ca_addr.primary_address, ''), NULLIF(ca_addr.any_address, ''), NULLIF(c.address, '')) as full_address");
    }

    private function primaryContactSelectExpression()
    {
        return DB::raw("COALESCE(NULLIF(cc_primary.primary_contact_detail, ''), NULLIF(cc_primary.any_contact_detail, '')) as primary_contact_detail");
    }

    private function primaryContactDisplaySubquery()
    {
        $detailExpr = "TRIM(CONCAT_WS(' | ', NULLIF(cc.name, ''), NULLIF(cc.designation, ''), NULLIF(cc.contact_number, ''), NULLIF(cc.email, '')))";

        return DB::table('client_contacts as cc')
            ->select('cc.client_id')
            ->selectRaw("MAX(CASE WHEN COALESCE(cc.sort_order, 999999) = 0 THEN {$detailExpr} END) as primary_contact_detail")
            ->selectRaw("MAX({$detailExpr}) as any_contact_detail")
            ->groupBy('cc.client_id');
    }

    private function additionalComment1SelectExpression()
    {
        return DB::raw("COALESCE(NULLIF(cd.additional_comment_1, ''), NULLIF(c.additional_notes, '')) as additional_comment_1");
    }

    private function additionalComment2SelectExpression()
    {
        return DB::raw("COALESCE(NULLIF(cd.additional_comment_2, ''), NULLIF(c.remarks, '')) as additional_comment_2");
    }

    /**
     * @param  array<int,mixed>  $columns
     * @return array<int,mixed>
     */
    private function uniqueSelectColumns(array $columns): array
    {
        $seen = [];
        $out = [];
        foreach ($columns as $column) {
            $key = is_string($column) ? $column : 'expr_' . spl_object_id($column);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $column;
        }

        return $out;
    }

    private function applyQueryTimeoutGuard(): void
    {
        try {
            DB::statement('SET SESSION max_execution_time=1000');
        } catch (\Throwable $e) {
            // Not all DB engines support this statement.
        }
    }

    /**
     * Apply role-based visibility filtering:
     * - superadmin / admin: see all
     * - manager: see clients where they are manager, or team members are assigned
     * - team_leader: see clients assigned to their team members or themselves
     * - CSR / sales_agent: see only clients assigned to them (sales_agent, created_by, csr_name columns)
     */
    private function applyUserVisibility($query, User $user): void
    {
        // Superadmin and admin see everything
        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            return;
        }

        // Get visible user IDs through team hierarchy
        $visibleUserIds = TeamHierarchyService::getVisibleUserIds($user);
        if (! in_array((int) $user->id, $visibleUserIds, true)) {
            $visibleUserIds[] = (int) $user->id;
        }
        $visibleUserIds = array_values(array_unique(array_map('intval', $visibleUserIds)));

        if (empty($visibleUserIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($q) use ($visibleUserIds, $user) {
            $q->whereIn('c.created_by', $visibleUserIds)
              ->orWhereIn('c.sales_agent_id', $visibleUserIds)
              ->orWhereIn('c.team_leader_id', $visibleUserIds)
              ->orWhereIn('c.manager_id', $visibleUserIds)
              ->orWhereIn('c.account_manager_id', $visibleUserIds);

            // Also match by CSR name columns
            $userName = $user->name;
            if ($userName) {
                $q->orWhere('c.csr_name_1', $userName)
                  ->orWhere('c.csr_name_2', $userName)
                  ->orWhere('c.csr_name_3', $userName);
            }
        });
    }
}

