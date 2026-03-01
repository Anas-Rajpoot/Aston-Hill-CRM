<?php

namespace App\Repositories;

use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class ClientRepository
{
    /**
     * Optimized clients listing query used by All Clients page.
     *
     * @param  array<string,mixed>  $params
     * @param  array<int,string>  $columns
     */
    public function list(array $params, array $columns = []): CursorPaginator
    {
        $this->applyQueryTimeoutGuard();

        $perPage = (int) ($params['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 50));
        $sort = (string) ($params['sort'] ?? 'submitted_at');
        $order = strtolower((string) ($params['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('clients as c')
            ->leftJoin('users as m', 'm.id', '=', 'c.manager_id')
            ->leftJoin('users as tl', 'tl.id', '=', 'c.team_leader_id')
            ->leftJoin('users as sa', 'sa.id', '=', 'c.sales_agent_id')
            ->leftJoin('users as cr', 'cr.id', '=', 'c.created_by')
            ->leftJoin('client_company_details as cd', 'cd.client_id', '=', 'c.id');

        $selects = $this->resolveSelectColumns($columns);
        $query->select($selects);

        $this->applyFilters($query, $params);

        [$sortColumn, $sortNeedsRaw] = $this->resolveSortColumn($sort);
        if ($sortNeedsRaw) {
            $query->orderByRaw("{$sortColumn} {$order}");
        } else {
            $query->orderBy($sortColumn, $order);
        }
        // Stable secondary sort for cursor pagination.
        if ($sortColumn !== 'c.id') {
            $query->orderBy('c.id', 'desc');
        }

        return $query->cursorPaginate($perPage);
    }

    /**
     * @param  array<int,string>  $columns
     * @return array<int,string>
     */
    private function resolveSelectColumns(array $columns): array
    {
        $default = [
            'c.id',
            'c.company_name',
            'c.account_number',
            'c.status',
            'c.submitted_at',
            'c.activation_date',
            'c.completion_date',
            'c.contract_end_date',
            'c.service_category',
            'c.service_type',
            'c.product_type',
            'c.product_name',
            'c.work_order_status',
            'c.payment_connection',
            'c.contract_type',
            'c.clawback_chum',
            'c.remarks',
            'c.mrc',
            'c.created_at',
            'm.name as manager',
            'tl.name as team_leader',
            'sa.name as sales_agent',
            'cr.name as creator',
            'cd.company_category',
            'cd.trade_license_expiry_date',
            'cd.establishment_card_expiry_date',
            'cd.account_transfer_given_to',
            'cd.account_manager_name',
        ];

        if (empty($columns)) {
            return $default;
        }

        $map = [
            'id' => 'c.id',
            'company_name' => 'c.company_name',
            'account_number' => 'c.account_number',
            'status' => 'c.status',
            'submitted_at' => 'c.submitted_at',
            'activation_date' => 'c.activation_date',
            'completion_date' => 'c.completion_date',
            'contract_end_date' => 'c.contract_end_date',
            'service_category' => 'c.service_category',
            'service_type' => 'c.service_type',
            'product_type' => 'c.product_type',
            'product_name' => 'c.product_name',
            'work_order_status' => 'c.work_order_status',
            'payment_connection' => 'c.payment_connection',
            'contract_type' => 'c.contract_type',
            'clawback_chum' => 'c.clawback_chum',
            'remarks' => 'c.remarks',
            'mrc' => 'c.mrc',
            'manager' => 'm.name as manager',
            'team_leader' => 'tl.name as team_leader',
            'sales_agent' => 'sa.name as sales_agent',
            'creator' => 'cr.name as creator',
            'company_category' => 'cd.company_category',
            'trade_license_expiry_date' => 'cd.trade_license_expiry_date',
            'establishment_card_expiry_date' => 'cd.establishment_card_expiry_date',
            'account_transfer_given_to' => 'cd.account_transfer_given_to',
            'account_manager_name' => 'cd.account_manager_name',
            'created_at' => 'c.created_at',
        ];

        $resolved = ['c.id'];
        foreach ($columns as $column) {
            if (isset($map[$column])) {
                $resolved[] = $map[$column];
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private function applyFilters($query, array $params): void
    {
        $prefixLike = static function ($value): string {
            return addcslashes((string) $value, '%_\\') . '%';
        };
        $dayStart = static fn (string $date): string => $date . ' 00:00:00';
        $dayEnd = static fn (string $date): string => $date . ' 23:59:59';

        $query->when(! empty($params['status']), fn ($q) => $q->where('c.status', (string) $params['status']))
            ->when(! empty($params['company_name']), fn ($q) => $q->where('c.company_name', 'like', $prefixLike($params['company_name'])))
            ->when(! empty($params['account_number']), fn ($q) => $q->where('c.account_number', 'like', $prefixLike($params['account_number'])))
            ->when(! empty($params['manager_id']), fn ($q) => $q->where('c.manager_id', (int) $params['manager_id']))
            ->when(! empty($params['team_leader_id']), fn ($q) => $q->where('c.team_leader_id', (int) $params['team_leader_id']))
            ->when(! empty($params['sales_agent_id']), fn ($q) => $q->where('c.sales_agent_id', (int) $params['sales_agent_id']))
            ->when(! empty($params['service_category']), fn ($q) => $q->where('c.service_category', 'like', $prefixLike($params['service_category'])))
            ->when(! empty($params['service_type']), fn ($q) => $q->where('c.service_type', 'like', $prefixLike($params['service_type'])))
            ->when(! empty($params['product_type']), fn ($q) => $q->where('c.product_type', 'like', $prefixLike($params['product_type'])))
            ->when(! empty($params['product_name']), fn ($q) => $q->where('c.product_name', 'like', $prefixLike($params['product_name'])))
            ->when(! empty($params['work_order_status']), fn ($q) => $q->where('c.work_order_status', 'like', $prefixLike($params['work_order_status'])))
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
            default => [in_array($sort, ['id', 'company_name', 'account_number', 'status', 'submitted_at', 'activation_date', 'completion_date', 'contract_end_date', 'service_category', 'service_type', 'product_type', 'product_name', 'work_order_status', 'payment_connection', 'contract_type', 'created_at'], true) ? 'c.' . $sort : 'c.submitted_at', false],
        };
    }

    private function applyQueryTimeoutGuard(): void
    {
        try {
            DB::statement('SET SESSION max_execution_time=1000');
        } catch (\Throwable $e) {
            // Not all DB engines support this statement.
        }
    }
}

