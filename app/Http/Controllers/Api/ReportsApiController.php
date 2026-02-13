<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Models\VasRequestSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Report statistics and aggregations for Lead, Field Operations, and VAS Reports.
 */
class ReportsApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * GET /api/reports/lead-stats
     * KPIs, by_status, by_category, monthly_trend for Lead Reports page.
     */
    public function leadStats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeadSubmission::class);

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'status' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(LeadSubmission::STATUSES)],
            'service_category_id' => ['sometimes', 'nullable', 'integer', 'exists:service_categories,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $query = LeadSubmission::query()->visibleTo($request->user());
        $this->applyLeadFilters($query, $validated);

        $totalLeads = (clone $query)->count();

        $resubmissions = (clone $query)->where('submission_type', 'resubmission')->count();
        $newSubmissions = (clone $query)->whereNotNull('submitted_at')
            ->where(function ($q) {
                $q->whereNull('submission_type')->orWhere('submission_type', '!=', 'resubmission');
            })->count();

        $byStatus = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byCategory = (clone $query)
            ->leftJoin('service_categories', 'lead_submissions.service_category_id', '=', 'service_categories.id')
            ->select('service_categories.name as category_name', DB::raw('count(lead_submissions.id) as count'))
            ->groupBy('service_categories.id', 'service_categories.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['name' => $r->category_name ?? 'Uncategorized', 'count' => (int) $r->count])
            ->toArray();

        $monthlyTrend = (clone $query)
            ->select(DB::raw('YEAR(submitted_at) as year'), DB::raw('MONTH(submitted_at) as month'), DB::raw('count(*) as count'))
            ->whereNotNull('submitted_at')
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'label' => date('M Y', mktime(0, 0, 0, (int) $r->month, 1, (int) $r->year)),
                'count' => (int) $r->count,
            ])
            ->toArray();

        $approved = (int) ($byStatus['approved'] ?? 0);
        $slaCompliance = $totalLeads > 0 ? round(100 * $approved / $totalLeads, 1) : 0;

        $avgDays = (clone $query)
            ->whereNotNull('submitted_at')
            ->whereNotNull('status_changed_at')
            ->selectRaw('AVG(DATEDIFF(status_changed_at, submitted_at)) as avg_days')
            ->value('avg_days');
        $avgProcessingDays = $avgDays !== null ? round((float) $avgDays, 1) : 0;

        return response()->json([
            'total_leads' => $totalLeads,
            'new_submissions' => $newSubmissions,
            'resubmissions' => $resubmissions,
            'sla_compliance_pct' => $slaCompliance,
            'avg_processing_days' => $avgProcessingDays,
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'monthly_trend' => $monthlyTrend,
        ]);
    }

    /**
     * GET /api/reports/field-stats
     * KPIs, by_status (field_status), by_agent_workload, completion_rate by month.
     */
    public function fieldStats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FieldSubmission::class);

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'status' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(FieldSubmission::STATUSES)],
            'emirates' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'field_executive_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $query = FieldSubmission::query()->visibleTo($request->user());
        $this->applyFieldFilters($query, $validated);

        $totalRequests = (clone $query)->count();

        $completedStatuses = ['Survey Completed', 'Completed', 'Visited'];
        $meetingsCompleted = (clone $query)->whereIn('field_status', $completedStatuses)->count();
        $cancellations = (clone $query)->where('field_status', 'Cancelled')->count();
        $followUps = (clone $query)->where('field_status', 'Rescheduled')->count();

        $slaBreaches = (clone $query)->whereNotNull('meeting_date')
            ->where('meeting_date', '<', now()->startOfDay())
            ->where(function ($q) use ($completedStatuses) {
                $q->whereNotIn('field_status', $completedStatuses)->orWhereNull('field_status');
            })
            ->count();

        $byStatus = (clone $query)->select('field_status as status', DB::raw('count(*) as count'))
            ->whereNotNull('field_status')
            ->where('field_status', '!=', '')
            ->groupBy('field_status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['name' => $r->status, 'count' => (int) $r->count])
            ->toArray();

        $byAgent = (clone $query)
            ->join('users', 'field_submissions.field_executive_id', '=', 'users.id')
            ->select('users.name', DB::raw('count(field_submissions.id) as count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])
            ->toArray();

        $completionByMonth = (clone $query)
            ->select(DB::raw('YEAR(meeting_date) as year'), DB::raw('MONTH(meeting_date) as month'), DB::raw('count(*) as total'))
            ->whereNotNull('meeting_date')
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->get();

        $completedByMonth = (clone $query)
            ->whereIn('field_status', $completedStatuses)
            ->whereNotNull('meeting_date')
            ->select(DB::raw('YEAR(meeting_date) as year'), DB::raw('MONTH(meeting_date) as month'), DB::raw('count(*) as completed'))
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn ($r) => (int) $r->year . '-' . (int) $r->month);

        $completionRateByMonth = $completionByMonth->map(fn ($r) => [
            'label' => date('M Y', mktime(0, 0, 0, (int) $r->month, 1, (int) $r->year)),
            'total' => (int) $r->total,
            'completed' => (int) ($completedByMonth->get((int) $r->year . '-' . (int) $r->month)?->completed ?? 0),
            'pct' => $r->total > 0 ? round(100 * ($completedByMonth->get((int) $r->year . '-' . (int) $r->month)?->completed ?? 0) / $r->total, 0) : 0,
        ])->toArray();

        return response()->json([
            'total_requests' => $totalRequests,
            'meetings_completed' => $meetingsCompleted,
            'cancellations' => $cancellations,
            'follow_ups' => $followUps,
            'sla_breaches' => $slaBreaches,
            'by_status' => $byStatus,
            'by_agent_workload' => $byAgent,
            'completion_rate_by_month' => array_values($completionRateByMonth),
        ]);
    }

    /**
     * GET /api/reports/vas-stats
     * KPIs for VAS Reports: total, pending, completed_today, sla_compliance_pct.
     */
    public function vasStats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VasRequestSubmission::class);

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'submitted_from' => ['sometimes', 'nullable', 'date'],
            'submitted_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:submitted_from'],
            'status' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(VasRequestSubmission::STATUSES)],
            'request_type' => ['sometimes', 'nullable', 'string', 'max:150'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $query = VasRequestSubmission::query();
        $this->applyVasFilters($query, $validated);

        $total = (clone $query)->count();
        $pending = (clone $query)->whereIn('status', ['draft', 'submitted'])->count();
        $todayStart = now()->startOfDay()->toDateTimeString();
        $todayEnd = now()->endOfDay()->toDateTimeString();
        $completedToday = (clone $query)->where('status', 'approved')->whereBetween('approved_at', [$todayStart, $todayEnd])->count();

        $slaDays = (int) config('modules.vas_request_submissions.sla_days', 7);
        $approvedQuery = (clone $query)->where('status', 'approved')->whereNotNull('submitted_at')->whereNotNull('approved_at');
        $approvedTotal = $approvedQuery->count();
        $withinSla = (clone $approvedQuery)->whereRaw("DATEDIFF(approved_at, submitted_at) <= ?", [$slaDays])->count();
        $slaCompliancePct = $approvedTotal > 0 ? round(100 * $withinSla / $approvedTotal, 1) : 0;

        return response()->json([
            'total_vas_requests' => $total,
            'pending_requests' => $pending,
            'completed_today' => $completedToday,
            'sla_compliance_pct' => $slaCompliancePct,
        ]);
    }

    private function applyLeadFilters($query, array $validated): void
    {
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['service_category_id'])) {
            $query->where('service_category_id', $validated['service_category_id']);
        }
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from'] . ' 00:00:00');
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }
        if (! empty($validated['submitted_from'])) {
            $query->where('submitted_at', '>=', $validated['submitted_from'] . ' 00:00:00');
        }
        if (! empty($validated['submitted_to'])) {
            $query->where('submitted_at', '<=', $validated['submitted_to'] . ' 23:59:59');
        }
        if (! empty($validated['sales_agent_id'])) {
            $query->where('sales_agent_id', $validated['sales_agent_id']);
        }
        if (! empty($validated['team_leader_id'])) {
            $query->where('team_leader_id', $validated['team_leader_id']);
        }
    }

    private function applyFieldFilters($query, array $validated): void
    {
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from'] . ' 00:00:00');
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }
        if (! empty($validated['submitted_from'])) {
            $query->where('submitted_at', '>=', $validated['submitted_from'] . ' 00:00:00');
        }
        if (! empty($validated['submitted_to'])) {
            $query->where('submitted_at', '<=', $validated['submitted_to'] . ' 23:59:59');
        }
        if (! empty($validated['emirates'])) {
            $query->where('emirates', 'like', '%' . addcslashes($validated['emirates'], '%_\\') . '%');
        }
        if (! empty($validated['sales_agent_id'])) {
            $query->where('sales_agent_id', $validated['sales_agent_id']);
        }
        if (! empty($validated['team_leader_id'])) {
            $query->where('team_leader_id', $validated['team_leader_id']);
        }
        if (! empty($validated['field_executive_id'])) {
            $query->where('field_executive_id', $validated['field_executive_id']);
        }
    }

    private function applyVasFilters($query, array $validated): void
    {
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from'] . ' 00:00:00');
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }
        if (! empty($validated['submitted_from'])) {
            $query->where('submitted_at', '>=', $validated['submitted_from'] . ' 00:00:00');
        }
        if (! empty($validated['submitted_to'])) {
            $query->where('submitted_at', '<=', $validated['submitted_to'] . ' 23:59:59');
        }
        if (! empty($validated['request_type'])) {
            $query->where('request_type', $validated['request_type']);
        }
        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'like', $term)
                    ->orWhere('account_number', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }
    }
}
