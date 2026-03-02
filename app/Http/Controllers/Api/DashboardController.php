<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\CustomerSupportSubmission;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Models\SystemPreference;
use App\Models\User;
use App\Models\VasRequestSubmission;
use App\Services\TeamHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const VERSION_KEY = 'dashboard_stats_version';

    /**
     * GET /api/dashboard/stats
     *
     * Returns KPI counters, recent activity, and auto-refresh config.
     * Supports ETag / If-None-Match for 304 responses.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $cacheKey = "dashboard_stats:v{$version}:u{$user->id}";
        $cacheTtl = 120; // 2 min

        $data = Cache::remember($cacheKey, $cacheTtl, function () use ($user) {
            return $this->buildStats($user);
        });

        // Compute ETag from data hash
        $etag = '"' . md5(json_encode($data)) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        // Include auto-refresh settings
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

    private function buildStats(User $user): array
    {
        $today = now()->startOfDay();
        $weekAgo = now()->subDays(7);
        $visibleUserIds = $this->visibleUserIds($user);

        $leadQuery = LeadSubmission::query();
        $fieldQuery = FieldSubmission::query();
        $supportQuery = CustomerSupportSubmission::query();
        $vasQuery = VasRequestSubmission::query();
        $clientQuery = Client::query();

        $this->scopeByVisibleUsers($leadQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'executive_id']);
        $this->scopeByVisibleUsers($fieldQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'field_executive_id']);
        $this->scopeByVisibleUsers($supportQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'csr_id']);
        $this->scopeByVisibleUsers($vasQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'back_office_executive_id']);
        $this->scopeByVisibleUsers($clientQuery, $user, ['created_by', 'sales_agent_id', 'team_leader_id', 'manager_id', 'account_manager_id']);

        return [
            'kpis' => [
                'total_leads'        => (clone $leadQuery)->count(),
                'leads_today'        => (clone $leadQuery)->where('created_at', '>=', $today)->count(),
                'field_submissions'  => (clone $fieldQuery)->count(),
                'field_today'        => (clone $fieldQuery)->where('created_at', '>=', $today)->count(),
                'support_tickets'    => (clone $supportQuery)->count(),
                'support_open'       => (clone $supportQuery)->where('status', 'open')->count(),
                'vas_requests'       => (clone $vasQuery)->count(),
                'vas_pending'        => (clone $vasQuery)->where('status', 'pending')->count(),
                'total_clients'      => (clone $clientQuery)->count(),
                'active_users'       => $user->hasRole('superadmin')
                    ? User::where('updated_at', '>=', $weekAgo)->count()
                    : User::whereIn('id', $visibleUserIds)->where('updated_at', '>=', $weekAgo)->count(),
            ],
            'recent_activity' => $this->recentActivity($user),
            'generated_at'    => now()->toIso8601String(),
        ];
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

        // Recent lead submissions
        $leadQuery->orderByDesc('created_at')->limit(5)->get(['id', 'company_name', 'account_number', 'status', 'created_at'])->each(function ($r) use ($items) {
            $items->push([
                'type'      => 'lead',
                'label'     => 'Lead ' . ($r->account_number ?? '#' . $r->id),
                'detail'    => $r->company_name ?? '—',
                'status'    => $r->status ?? 'new',
                'timestamp' => $r->created_at?->toIso8601String(),
            ]);
        });

        // Recent field submissions
        $fieldQuery->orderByDesc('created_at')->limit(5)->get(['id', 'company_name', 'status', 'created_at'])->each(function ($r) use ($items) {
            $items->push([
                'type'      => 'field',
                'label'     => 'Field #' . $r->id,
                'detail'    => $r->company_name ?? '—',
                'status'    => $r->status ?? 'new',
                'timestamp' => $r->created_at?->toIso8601String(),
            ]);
        });

        // Recent support tickets
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

    /**
     * Get all user IDs visible to the current user.
     *
     * @return int[]
     */
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

    /**
     * Scope a query to visible users for non-superadmin users.
     *
     * @param  array<int,string>  $columns
     */
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
     * Called after any mutation to bust the dashboard cache.
     */
    public static function clearCache(): void
    {
        Cache::put(self::VERSION_KEY, time(), 86400);
    }
}
