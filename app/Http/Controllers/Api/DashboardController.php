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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/stats
     *
     * Returns KPI counters, recent activity, and auto-refresh config.
     * Supports ETag / If-None-Match for 304 responses.
     */
    public function stats(Request $request): JsonResponse
    {
        $cacheKey = 'dashboard_stats';
        $cacheTtl = 120; // 2 min

        $data = Cache::remember($cacheKey, $cacheTtl, function () {
            return $this->buildStats();
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

    private function buildStats(): array
    {
        $today = now()->startOfDay();
        $weekAgo = now()->subDays(7);

        return [
            'kpis' => [
                'total_leads'        => LeadSubmission::count(),
                'leads_today'        => LeadSubmission::where('created_at', '>=', $today)->count(),
                'field_submissions'  => FieldSubmission::count(),
                'field_today'        => FieldSubmission::where('created_at', '>=', $today)->count(),
                'support_tickets'    => CustomerSupportSubmission::count(),
                'support_open'       => CustomerSupportSubmission::where('status', 'open')->count(),
                'vas_requests'       => VasRequestSubmission::count(),
                'vas_pending'        => VasRequestSubmission::where('status', 'pending')->count(),
                'total_clients'      => Client::count(),
                'active_users'       => User::where('updated_at', '>=', $weekAgo)->count(),
            ],
            'recent_activity' => $this->recentActivity(),
            'generated_at'    => now()->toIso8601String(),
        ];
    }

    private function recentActivity(): array
    {
        $items = collect();

        // Recent lead submissions
        LeadSubmission::orderByDesc('created_at')->limit(5)->get(['id', 'ref_no', 'applicant_full_name', 'status', 'created_at'])->each(function ($r) use ($items) {
            $items->push([
                'type'      => 'lead',
                'label'     => 'Lead ' . ($r->ref_no ?? '#' . $r->id),
                'detail'    => $r->applicant_full_name ?? '—',
                'status'    => $r->status ?? 'new',
                'timestamp' => $r->created_at?->toIso8601String(),
            ]);
        });

        // Recent field submissions
        FieldSubmission::orderByDesc('created_at')->limit(5)->get(['id', 'ref_no', 'applicant_full_name', 'status', 'created_at'])->each(function ($r) use ($items) {
            $items->push([
                'type'      => 'field',
                'label'     => 'Field ' . ($r->ref_no ?? '#' . $r->id),
                'detail'    => $r->applicant_full_name ?? '—',
                'status'    => $r->status ?? 'new',
                'timestamp' => $r->created_at?->toIso8601String(),
            ]);
        });

        // Recent support tickets
        CustomerSupportSubmission::orderByDesc('created_at')->limit(3)->get(['id', 'subject', 'status', 'created_at'])->each(function ($r) use ($items) {
            $items->push([
                'type'      => 'support',
                'label'     => 'Support #' . $r->id,
                'detail'    => $r->subject ?? '—',
                'status'    => $r->status ?? 'open',
                'timestamp' => $r->created_at?->toIso8601String(),
            ]);
        });

        return $items->sortByDesc('timestamp')->take(10)->values()->toArray();
    }

    /**
     * Called after any mutation to bust the dashboard cache.
     */
    public static function clearCache(): void
    {
        Cache::forget('dashboard_stats');
    }
}
