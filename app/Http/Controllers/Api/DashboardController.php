<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\CustomerSupportSubmission;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Models\SpecialRequest;
use App\Models\ServiceCategory;
use App\Models\SystemPreference;
use App\Models\Team;
use App\Models\User;
use App\Models\UserMonthlyTargetHistory;
use App\Models\VasRequestSubmission;
use App\Services\TeamHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const VERSION_KEY = 'dashboard_stats_version';

    /**
     * GET /api/dashboard/stats
     *
     * Returns KPI counters, recent activity, form summary, and auto-refresh config.
     * Supports date_from, date_to, team_id, csr_id filters.
     * Supports ETag / If-None-Match for 304 responses.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['date_from', 'date_to', 'team_id', 'csr_id']);
        $filterHash = md5(json_encode($filters));
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $cacheKey = "dashboard_stats:v{$version}:u{$user->id}:f{$filterHash}";
        $cacheTtl = 120; // 2 min

        $data = Cache::remember($cacheKey, $cacheTtl, function () use ($user, $filters) {
            return $this->buildStats($user, $filters);
        });

        $etag = '"' . md5(json_encode($data)) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        $prefs = SystemPreference::singleton();

        return response()->json([
            'data'         => $data,
            'auto_refresh' => [
                'enabled'          => (bool) $prefs->auto_refresh_dashboard,
                'interval_minutes' => (int) $prefs->auto_refresh_interval_minutes,
            ],
        ])
        ->header('ETag', $etag)
        ->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * GET /api/dashboard/filters
     * Returns filter options for the dashboard.
     */
    public function filters(Request $request): JsonResponse
    {
        $teams = Team::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $csrs = User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['csr', 'sales_agent', 'team_leader', 'manager']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'teams' => $teams,
            'csrs'  => $csrs,
        ]);
    }

    private function buildStats(User $user, array $filters = []): array
    {
        $today = now()->startOfDay();
        $weekAgo = now()->subDays(7);
        $visibleUserIds = $this->visibleUserIds($user);

        $leadQuery = LeadSubmission::query();
        $fieldQuery = FieldSubmission::query();
        $supportQuery = CustomerSupportSubmission::query();
        $vasQuery = VasRequestSubmission::query();
        $specialQuery = SpecialRequest::query();
        $clientQuery = Client::query();

        $this->scopeByVisibleUsers($leadQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'executive_id']);
        $this->scopeByVisibleUsers($fieldQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'field_executive_id']);
        $this->scopeByVisibleUsers($supportQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'csr_id']);
        $this->scopeByVisibleUsers($vasQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'back_office_executive_id']);
        $this->scopeByVisibleUsers($specialQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id']);
        $this->scopeByVisibleUsers($clientQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'account_manager_id']);

        // Apply date filters
        foreach ([$leadQuery, $fieldQuery, $supportQuery, $vasQuery, $specialQuery, $clientQuery] as $q) {
            $this->applyDateFilters($q, $filters);
        }

        // Apply team filter
        if (!empty($filters['team_id'])) {
            $teamId = (int) $filters['team_id'];
            foreach ([$leadQuery, $fieldQuery, $supportQuery, $vasQuery] as $q) {
                if (\Schema::hasColumn($q->getModel()->getTable(), 'team_id')) {
                    $q->where('team_id', $teamId);
                }
            }
        }

        // Apply CSR filter
        if (!empty($filters['csr_id'])) {
            $csrId = (int) $filters['csr_id'];
            $leadQuery->where(fn ($q) => $q->where('sales_agent_id', $csrId)->orWhere('created_by', $csrId));
            $fieldQuery->where(fn ($q) => $q->where('sales_agent_id', $csrId)->orWhere('created_by', $csrId));
            $supportQuery->where(fn ($q) => $q->where('csr_id', $csrId)->orWhere('sales_agent_id', $csrId)->orWhere('created_by', $csrId));
            $vasQuery->where(fn ($q) => $q->where('sales_agent_id', $csrId)->orWhere('created_by', $csrId));
        }

        $totalTeams = Team::where('status', 'active')->count();
        $totalEmployees = User::where('status', 'active')->count();

        return [
            'kpis' => [
                'total_clients'      => (clone $clientQuery)->count(),
                'active_deals'       => (clone $leadQuery)->whereIn('status', ['submitted', 'approved', 'pending_from_sales', 'pending_for_finance', 'pending_for_ata'])->count(),
                'total_leads'        => (clone $leadQuery)->count(),
                'leads_today'        => (clone $leadQuery)->where('created_at', '>=', $today)->count(),
                'total_teams'        => $totalTeams,
                'total_employees'    => $totalEmployees,
                'field_submissions'  => (clone $fieldQuery)->count(),
                'field_today'        => (clone $fieldQuery)->where('created_at', '>=', $today)->count(),
                'support_tickets'    => (clone $supportQuery)->count(),
                'support_open'       => (clone $supportQuery)->where('status', 'open')->count(),
                'vas_requests'       => (clone $vasQuery)->count(),
                'vas_pending'        => (clone $vasQuery)->where('status', 'pending')->count(),
                'special_requests'   => (clone $specialQuery)->count(),
                'active_users'       => $user->hasRole('superadmin')
                    ? User::where('updated_at', '>=', $weekAgo)->count()
                    : User::whereIn('id', $visibleUserIds)->where('updated_at', '>=', $weekAgo)->count(),
                'total_target_mrc'   => $this->totalTargetMrc($user, $filters),
            ],
            'form_summary'    => $this->formSummary($user, $filters),
            'recent_activity' => $this->recentActivity($user),
            'generated_at'    => now()->toIso8601String(),
        ];
    }

    /**
     * Build the 5-forms summary table: rows = service categories, columns = statuses.
     * Each form type contributes rows with mrc_total and qty.
     */
    private function formSummary(User $user, array $filters = []): array
    {
        $categories = ServiceCategory::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id');

        // Aggregate across the 5 form types
        $formTypes = [
            'Lead Submissions'    => $this->formSummaryForModel(LeadSubmission::class, $user, $filters, 'service_category_id', 'mrc_aed'),
            'Field Submissions'   => $this->formSummaryForModel(FieldSubmission::class, $user, $filters, null, null),
            'Customer Support'    => $this->formSummaryForModel(CustomerSupportSubmission::class, $user, $filters, null, null),
            'VAS Requests'        => $this->formSummaryForModel(VasRequestSubmission::class, $user, $filters, null, null),
            'Special Requests'    => $this->formSummaryForModel(SpecialRequest::class, $user, $filters, null, null),
        ];

        // Collect all unique statuses
        $allStatuses = collect();
        foreach ($formTypes as $rows) {
            foreach ($rows as $row) {
                $allStatuses->push($row['status']);
            }
        }
        $statuses = $allStatuses->unique()->sort()->values()->toArray();

        return [
            'categories'  => $categories,
            'statuses'    => $statuses,
            'form_types'  => $formTypes,
        ];
    }

    private function formSummaryForModel(string $modelClass, User $user, array $filters, ?string $categoryCol, ?string $mrcCol): array
    {
        $query = $modelClass::query();

        // Apply visibility scope
        $userCols = match ($modelClass) {
            LeadSubmission::class            => ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'executive_id'],
            FieldSubmission::class           => ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'field_executive_id'],
            CustomerSupportSubmission::class => ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'csr_id'],
            VasRequestSubmission::class      => ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'back_office_executive_id'],
            SpecialRequest::class            => ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id'],
            default                          => ['created_by'],
        };

        $this->scopeByVisibleUsers($query, $user, $userCols);
        $this->applyDateFilters($query, $filters);

        if (!empty($filters['team_id']) && \Schema::hasColumn((new $modelClass)->getTable(), 'team_id')) {
            $query->where('team_id', (int) $filters['team_id']);
        }

        $select = ['status', DB::raw('COUNT(*) as qty')];
        $groupBy = ['status'];

        if ($categoryCol && \Schema::hasColumn((new $modelClass)->getTable(), $categoryCol)) {
            $select[] = $categoryCol;
            $groupBy[] = $categoryCol;
        }

        if ($mrcCol && \Schema::hasColumn((new $modelClass)->getTable(), $mrcCol)) {
            $select[] = DB::raw("COALESCE(SUM({$mrcCol}), 0) as mrc_total");
        } else {
            $select[] = DB::raw('0 as mrc_total');
        }

        return $query->select($select)->groupBy($groupBy)->get()->map(function ($row) use ($categoryCol) {
            return [
                'category_id' => $categoryCol ? ($row->{$categoryCol} ?? null) : null,
                'status'       => $row->status ?? 'unknown',
                'qty'          => (int) $row->qty,
                'mrc_total'    => (float) $row->mrc_total,
            ];
        })->toArray();
    }

    private function applyDateFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    private function recentActivity(User $user): array
    {
        $items = collect();
        $leadQuery = LeadSubmission::query();
        $fieldQuery = FieldSubmission::query();
        $supportQuery = CustomerSupportSubmission::query();

        $this->scopeByVisibleUsers($leadQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'executive_id']);
        $this->scopeByVisibleUsers($fieldQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'field_executive_id']);
        $this->scopeByVisibleUsers($supportQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'csr_id']);

        $leadQuery->orderByDesc('created_at')->limit(5)->get(['id', 'company_name', 'account_number', 'status', 'created_at'])->each(function ($r) use ($items) {
            $items->push([
                'type'      => 'lead',
                'label'     => 'Lead ' . ($r->account_number ?? '#' . $r->id),
                'detail'    => $r->company_name ?? '—',
                'status'    => $r->status ?? 'new',
                'timestamp' => $r->created_at?->toIso8601String(),
            ]);
        });

        $fieldQuery->orderByDesc('created_at')->limit(5)->get(['id', 'company_name', 'status', 'created_at'])->each(function ($r) use ($items) {
            $items->push([
                'type'      => 'field',
                'label'     => 'Field #' . $r->id,
                'detail'    => $r->company_name ?? '—',
                'status'    => $r->status ?? 'new',
                'timestamp' => $r->created_at?->toIso8601String(),
            ]);
        });

        $supportQuery->orderByDesc('created_at')->limit(3)->get(['id', 'issue_category', 'status', 'created_at'])->each(function ($r) use ($items) {
            $items->push([
                'type'      => 'support',
                'label'     => 'Support #' . $r->id,
                'detail'    => $r->issue_category ?? '—',
                'status'    => $r->status ?? 'open',
                'timestamp' => $r->created_at?->toIso8601String(),
            ]);
        });

        return $items->sortByDesc('timestamp')->take(10)->values()->toArray();
    }

    private function visibleUserIds(User $user): array
    {
        if ($user->hasRole('superadmin')) {
            return [];
        }

        $ids = TeamHierarchyService::getVisibleUserIds($user);
        if (! in_array((int) $user->id, $ids, true)) {
            $ids[] = (int) $user->id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function scopeByVisibleUsers(Builder $query, User $user, array $columns): void
    {
        if ($user->hasRole('superadmin')) {
            return;
        }

        $visibleUserIds = $this->visibleUserIds($user);
        if (empty($visibleUserIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($q) use ($columns, $visibleUserIds) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $q->whereIn($column, $visibleUserIds);
                    continue;
                }
                $q->orWhereIn($column, $visibleUserIds);
            }
        });
    }

    /**
     * Calculate the total Target MRC for the current month.
     * Uses the latest target per user for the current month from UserMonthlyTargetHistory.
     * Respects team/csr filters when applied.
     */
    private function totalTargetMrc(User $user, array $filters = []): float
    {
        $currentMonth = now()->format('Y-m');

        // Build a subquery to get the latest target per user for this month
        $latestIds = DB::table('user_monthly_target_history')
            ->select(DB::raw('MAX(id) as id'))
            ->where('month', $currentMonth)
            ->groupBy('user_id');

        $query = UserMonthlyTargetHistory::query()
            ->joinSub($latestIds, 'latest', fn ($join) => $join->on('user_monthly_target_history.id', '=', 'latest.id'));

        // Apply team filter
        if (!empty($filters['team_id'])) {
            $teamUserIds = User::where('team_id', (int) $filters['team_id'])->pluck('id')->toArray();
            $query->whereIn('user_monthly_target_history.user_id', $teamUserIds);
        }

        // Apply CSR filter
        if (!empty($filters['csr_id'])) {
            $query->where('user_monthly_target_history.user_id', (int) $filters['csr_id']);
        }

        // Scope to visible users (non-superadmin)
        if (!$user->hasRole('superadmin')) {
            $visibleUserIds = $this->visibleUserIds($user);
            if (!empty($visibleUserIds)) {
                $query->whereIn('user_monthly_target_history.user_id', $visibleUserIds);
            } else {
                return 0;
            }
        }

        return (float) $query->sum('user_monthly_target_history.target_amount');
    }

    public static function clearCache(): void
    {
        Cache::put(self::VERSION_KEY, time(), 86400);
    }
}
