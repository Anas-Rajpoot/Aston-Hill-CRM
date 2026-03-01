<?php

namespace App\Repositories;

use App\Services\CacheKey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FilterRepository
{
    /**
     * Return filters for clients listing.
     *
     * @param  array<string,mixed>  $params
     * @param  User|null  $user
     * @return array<string,mixed>
     */
    public function forClients(array $params = [], ?User $user = null): array
    {
        $this->applyQueryTimeoutGuard();
        $key = CacheKey::make('clients:filters', $params, $user);
        $ttl = 900;

        $resolver = function (): array {
            $rows = DB::table('clients as c')
                ->leftJoin('client_company_details as cd', 'cd.client_id', '=', 'c.id')
                ->select([
                    'c.status',
                    'c.account_number',
                    'c.submission_type',
                    'c.service_category',
                    'c.service_type',
                    'c.product_type',
                    'c.work_order_status',
                    'c.payment_connection',
                    'c.contract_type',
                    'c.clawback_chum',
                    'c.manager_id',
                    'c.team_leader_id',
                    'c.sales_agent_id',
                    'cd.company_category',
                    'cd.account_manager_name',
                ])
                ->get();

            $distinctStrings = static function ($collection, string $key): array {
                return $collection->pluck($key)
                    ->filter(fn ($v) => is_string($v) ? trim($v) !== '' : ! is_null($v))
                    ->map(fn ($v) => is_string($v) ? trim($v) : $v)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            };

            $statusesRaw = $distinctStrings($rows, 'status');
            $statuses = collect($statusesRaw)->map(
                fn ($s) => ['value' => $s, 'label' => ucfirst(str_replace('_', ' ', (string) $s))]
            )->values()->all();

            $accountNumbers = array_slice($distinctStrings($rows, 'account_number'), 0, 500);

            $managerIds = $rows->pluck('manager_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
            $teamLeaderIds = $rows->pluck('team_leader_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
            $salesAgentIds = $rows->pluck('sales_agent_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
            $allIds = array_values(array_unique(array_merge($managerIds, $teamLeaderIds, $salesAgentIds)));

            $usersMap = DB::table('users')
                ->whereIn('id', $allIds)
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->keyBy('id');

            $toUserOptions = static function (array $ids, $usersMap): array {
                return collect($ids)->map(function (int $id) use ($usersMap) {
                    $u = $usersMap->get($id);
                    if (! $u) {
                        return null;
                    }
                    return ['id' => (int) $u->id, 'name' => $u->name, 'label' => $u->name];
                })->filter()->values()->all();
            };

            $managers = $toUserOptions($managerIds, $usersMap);
            $teamLeaders = $toUserOptions($teamLeaderIds, $usersMap);
            $salesAgents = $toUserOptions($salesAgentIds, $usersMap);

            $alertTypes = DB::table('client_alerts')
                ->whereNotNull('alert_type')
                ->where('alert_type', '!=', '')
                ->distinct()
                ->orderBy('alert_type')
                ->pluck('alert_type')
                ->values()
                ->all();

            return [
                'statuses' => $statuses,
                'managers' => $managers,
                'team_leaders' => $teamLeaders,
                'sales_agents' => $salesAgents,
                'account_numbers' => $accountNumbers,
                'alert_types' => $alertTypes,
                'submission_types' => $distinctStrings($rows, 'submission_type'),
                'service_categories' => $distinctStrings($rows, 'service_category'),
                'service_types' => $distinctStrings($rows, 'service_type'),
                'product_types' => $distinctStrings($rows, 'product_type'),
                'work_order_statuses' => $distinctStrings($rows, 'work_order_status'),
                'payment_connections' => $distinctStrings($rows, 'payment_connection'),
                'contract_types' => $distinctStrings($rows, 'contract_type'),
                'clawback_chum_options' => $distinctStrings($rows, 'clawback_chum'),
                'company_categories' => $distinctStrings($rows, 'company_category'),
                'account_manager_names' => $distinctStrings($rows, 'account_manager_name'),
            ];
        };

        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            return Cache::tags(['clients', 'filters'])->remember($key, $ttl, $resolver);
        }

        return Cache::remember($key, $ttl, $resolver);
    }

    private function applyQueryTimeoutGuard(): void
    {
        try {
            DB::statement('SET SESSION max_execution_time=1000');
        } catch (\Throwable $e) {
            // Some engines do not support this statement.
        }
    }
}

