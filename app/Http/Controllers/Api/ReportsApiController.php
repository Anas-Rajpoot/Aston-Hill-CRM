<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CustomerSupportSubmission;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Models\SlaRule;
use App\Models\VasRequestSubmission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Report statistics and aggregations for Lead, Field Operations, VAS, and SLA Reports.
 */
class ReportsApiController extends Controller
{
    public function __construct()
    {
        // Route group already enforces auth + web session middleware.
        // Keep this aligned to support session and token auth consistently.
        $this->middleware(['auth:web,sanctum']);
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
     * Super admin sees all; back office sees assigned; others see own submissions.
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
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'back_office_executive_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $query = VasRequestSubmission::query()->visibleTo($request->user());
        $this->applyVasFilters($query, $validated);

        $total = (clone $query)->count();
        $pending = (clone $query)->whereIn('status', ['draft', 'submitted'])->count();
        $todayStart = now()->startOfDay()->toDateTimeString();
        $todayEnd = now()->endOfDay()->toDateTimeString();
        $completedToday = (clone $query)->where('status', 'approved')->whereBetween('updated_at', [$todayStart, $todayEnd])->count();

        $slaDays = (int) config('modules.vas_request_submissions.sla_days', 7);
        $approvedQuery = (clone $query)->where('status', 'approved')->whereNotNull('submitted_at');
        $approvedTotal = $approvedQuery->count();
        $withinSla = (clone $approvedQuery)->whereRaw("DATEDIFF(updated_at, submitted_at) <= ?", [$slaDays])->count();
        $slaCompliancePct = $approvedTotal > 0 ? round(100 * $withinSla / $approvedTotal, 1) : 0;

        return response()->json([
            'total_vas_requests' => $total,
            'pending_requests' => $pending,
            'completed_today' => $completedToday,
            'sla_compliance_pct' => $slaCompliancePct,
        ]);
    }

    /**
     * GET /api/reports/sla-performance
     * Comprehensive SLA dashboard: KPIs, department breakdown, category bars,
     * priority stats, recent breaches, monthly trend, and auto-generated insights.
     */
    public function slaPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to'   => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null;
        $to   = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : null;

        // Cache the heavy SLA computation for 2 minutes per user + filter combination
        $cacheKey = 'sla_perf_' . $user->id . '_' . md5(json_encode([$from?->toDateString(), $to?->toDateString()]));
        $payload = Cache::remember($cacheKey, 120, function () use ($user, $from, $to) {
            return $this->computeSlaPerformance($user, $from, $to);
        });

        return response()->json($payload);
    }

    private function computeSlaPerformance($user, ?Carbon $from, ?Carbon $to): array
    {
        $now  = Carbon::now();

        $rules = SlaRule::cached()->keyBy('module_key');

        $modules = [
            'lead_submissions' => [
                'label'      => 'Lead Submissions',
                'department' => 'Back Office',
                'dept_sub'   => 'Lead Processing',
                'priority'   => 'High',
                'icon'       => 'document',
            ],
            'field_submissions' => [
                'label'      => 'Field Submissions',
                'department' => 'Field Operations',
                'dept_sub'   => 'Field Submissions',
                'priority'   => 'High',
                'icon'       => 'map',
            ],
            'customer_support_requests' => [
                'label'      => 'Customer Support Tickets',
                'department' => 'Customer Support',
                'dept_sub'   => 'Ticket Resolution',
                'priority'   => 'Medium',
                'icon'       => 'support',
            ],
            'vas_requests' => [
                'label'      => 'VAS Requests',
                'department' => 'VAS Operations',
                'dept_sub'   => 'VAS Processing',
                'priority'   => 'Medium',
                'icon'       => 'cog',
            ],
        ];

        $allBreaches = collect();
        $departments = [];
        $categories = [];
        $priorityBuckets = ['High' => ['total' => 0, 'on_time' => 0, 'at_risk' => 0, 'breached' => 0],
                            'Medium' => ['total' => 0, 'on_time' => 0, 'at_risk' => 0, 'breached' => 0],
                            'Low' => ['total' => 0, 'on_time' => 0, 'at_risk' => 0, 'breached' => 0]];
        $monthlyData = [];
        $globalTotals = ['total' => 0, 'on_time' => 0, 'at_risk' => 0, 'breached' => 0];

        foreach ($modules as $moduleKey => $meta) {
            $rule = $rules->get($moduleKey);
            $slaMins = $rule?->sla_duration_minutes ?? 480;
            $warnMins = $rule?->warning_threshold_minutes ?? 60;

            $rows = $this->fetchModuleRows($moduleKey, $user, $from, $to);

            $onTime = 0; $atRisk = 0; $breached = 0;
            $totalResponseMinutes = 0; $resolvedCount = 0;

            foreach ($rows as $row) {
                $submittedAt = $row->submitted_at ? Carbon::parse($row->submitted_at) : null;
                if (!$submittedAt) continue;

                $deadline = $submittedAt->copy()->addMinutes($slaMins);
                $warnStart = $deadline->copy()->subMinutes($warnMins);

                $resolvedAt = $this->getResolvedAt($moduleKey, $row);
                $slaStatus = 'on_time';

                if ($resolvedAt) {
                    $resolvedAt = Carbon::parse($resolvedAt);
                    $responseMinutes = $submittedAt->diffInMinutes($resolvedAt);
                    $totalResponseMinutes += $responseMinutes;
                    $resolvedCount++;
                    $slaStatus = $resolvedAt->lte($deadline) ? 'on_time' : 'breached';
                } else {
                    if ($now->gt($deadline)) {
                        $slaStatus = 'breached';
                    } elseif ($now->gte($warnStart)) {
                        $slaStatus = 'at_risk';
                    }
                }

                if ($slaStatus === 'on_time') $onTime++;
                elseif ($slaStatus === 'at_risk') $atRisk++;
                else $breached++;

                if ($slaStatus === 'breached') {
                    $actualMinutes = $resolvedAt
                        ? $submittedAt->diffInMinutes($resolvedAt)
                        : $submittedAt->diffInMinutes($now);
                    $breachMinutes = max(0, $actualMinutes - $slaMins);

                    $allBreaches->push([
                        'request_id'     => $this->formatRequestId($moduleKey, $row->id),
                        'category'       => $meta['label'],
                        'department'     => $meta['department'],
                        'priority'       => $meta['priority'],
                        'submitted_date' => $submittedAt->toDateString(),
                        'sla_target'     => $this->minutesToHuman($slaMins),
                        'actual_time'    => $this->minutesToHuman((int) $actualMinutes),
                        'breach_duration' => '+' . $this->minutesToHuman((int) $breachMinutes),
                        'assigned_to'    => $this->getAssignedName($moduleKey, $row),
                        'submitted_at_ts' => $submittedAt->timestamp,
                    ]);
                }

                $monthKey = $submittedAt->format('Y-m');
                if (!isset($monthlyData[$monthKey])) {
                    $monthlyData[$monthKey] = ['total' => 0, 'on_time' => 0];
                }
                $monthlyData[$monthKey]['total']++;
                if ($slaStatus === 'on_time') $monthlyData[$monthKey]['on_time']++;
            }

            $total = $onTime + $atRisk + $breached;
            $compliancePct = $total > 0 ? round(100 * $onTime / $total, 1) : 0;
            $avgHours = $resolvedCount > 0 ? round($totalResponseMinutes / $resolvedCount / 60, 1) : 0;

            $departments[] = [
                'name'            => $meta['department'],
                'subtitle'        => $meta['dept_sub'],
                'icon'            => $meta['icon'],
                'total_requests'  => $total,
                'on_time'         => $onTime,
                'at_risk'         => $atRisk,
                'breached'        => $breached,
                'compliance_pct'  => $compliancePct,
                'avg_response'    => $avgHours . ' hours',
            ];

            $categories[] = [
                'name'           => $meta['label'],
                'total'          => $total,
                'breached'       => $breached,
                'compliance_pct' => $compliancePct,
            ];

            $priorityBuckets[$meta['priority']]['total'] += $total;
            $priorityBuckets[$meta['priority']]['on_time'] += $onTime;
            $priorityBuckets[$meta['priority']]['at_risk'] += $atRisk;
            $priorityBuckets[$meta['priority']]['breached'] += $breached;

            $globalTotals['total'] += $total;
            $globalTotals['on_time'] += $onTime;
            $globalTotals['at_risk'] += $atRisk;
            $globalTotals['breached'] += $breached;
        }

        $gt = $globalTotals;
        $kpis = [
            'total_requests'  => $gt['total'],
            'on_time_pct'     => $gt['total'] > 0 ? round(100 * $gt['on_time'] / $gt['total'], 1) : 0,
            'on_time_count'   => $gt['on_time'],
            'at_risk_pct'     => $gt['total'] > 0 ? round(100 * $gt['at_risk'] / $gt['total'], 1) : 0,
            'at_risk_count'   => $gt['at_risk'],
            'breached_pct'    => $gt['total'] > 0 ? round(100 * $gt['breached'] / $gt['total'], 1) : 0,
            'breached_count'  => $gt['breached'],
        ];

        $priority = [];
        foreach ($priorityBuckets as $level => $b) {
            if ($b['total'] === 0) continue;
            $priority[] = [
                'level'          => $level,
                'total'          => $b['total'],
                'on_time'        => $b['on_time'],
                'at_risk'        => $b['at_risk'],
                'breached'       => $b['breached'],
                'compliance_pct' => round(100 * $b['on_time'] / $b['total'], 1),
            ];
        }

        ksort($monthlyData);
        $monthlyTrend = [];
        foreach (array_slice($monthlyData, -6, null, true) as $ym => $d) {
            $monthlyTrend[] = [
                'label'          => Carbon::createFromFormat('Y-m', $ym)->format('M'),
                'total'          => $d['total'],
                'compliance_pct' => $d['total'] > 0 ? round(100 * $d['on_time'] / $d['total'], 1) : 0,
            ];
        }

        $recentBreaches = $allBreaches
            ->sortByDesc('submitted_at_ts')
            ->take(50)
            ->map(fn ($b) => collect($b)->except('submitted_at_ts')->toArray())
            ->values()
            ->toArray();

        $insights = $this->generateInsights($departments, $priority, $kpis, $monthlyTrend);

        return [
            'kpis'            => $kpis,
            'departments'     => $departments,
            'categories'      => $categories,
            'priority'        => $priority,
            'monthly_trend'   => $monthlyTrend,
            'recent_breaches' => $recentBreaches,
            'insights'        => $insights,
        ];
    }

    /* ──── SLA helpers ──────────────────────────────────────────────── */

    /**
     * Fetch submission rows for a module with consistent RBAC via model scopes.
     * Uses the same visibleTo() scope as listing pages for all 4 types.
     */
    private function fetchModuleRows(string $moduleKey, $user, ?Carbon $from, ?Carbon $to): \Illuminate\Support\Collection
    {
        switch ($moduleKey) {
            case 'lead_submissions':
                $q = LeadSubmission::query()->visibleTo($user)
                    ->whereNotNull('submitted_at')->where('status', '!=', 'draft')
                    ->select(['id', 'submitted_at', 'status', 'status_changed_at', 'created_by',
                              'sales_agent_id', 'team_leader_id', 'manager_id', 'executive_id']);
                break;
            case 'field_submissions':
                $q = FieldSubmission::query()->visibleTo($user)
                    ->whereNotNull('submitted_at')->where('status', '!=', 'draft')
                    ->select(['id', 'submitted_at', 'field_status', 'updated_at', 'created_by',
                              'field_executive_id', 'sales_agent_id', 'team_leader_id', 'manager_id']);
                break;
            case 'customer_support_requests':
                $q = CustomerSupportSubmission::query()->visibleTo($user)
                    ->whereNotNull('submitted_at')->where('status', '!=', 'draft')
                    ->select(['id', 'submitted_at', 'workflow_status', 'completion_date', 'updated_at',
                              'created_by', 'sales_agent_id', 'team_leader_id', 'manager_id']);
                break;
            case 'vas_requests':
                $q = VasRequestSubmission::query()->visibleTo($user)
                    ->whereNotNull('submitted_at')->where('status', '!=', 'draft')
                    ->select(['id', 'submitted_at', 'status', 'updated_at', 'created_by',
                              'sales_agent_id', 'team_leader_id', 'manager_id', 'back_office_executive_id']);
                break;
            default:
                return collect();
        }

        if ($from) $q->where('submitted_at', '>=', $from);
        if ($to) $q->where('submitted_at', '<=', $to);

        return $q->get();
    }

    private function getResolvedAt(string $moduleKey, $row): ?string
    {
        switch ($moduleKey) {
            case 'lead_submissions':
                return in_array($row->status, ['approved', 'rejected']) ? ($row->status_changed_at ?? null) : null;
            case 'field_submissions':
                $done = ['Survey Completed', 'Completed', 'Visited'];
                return in_array($row->field_status, $done) ? ($row->updated_at ?? null) : null;
            case 'customer_support_requests':
                if (in_array($row->workflow_status, ['resolved', 'closed'])) {
                    return $row->completion_date ?? $row->updated_at ?? null;
                }
                return null;
            case 'vas_requests':
                if (in_array($row->status, ['approved', 'rejected'])) {
                    return $row->updated_at ?? null;
                }
                return null;
            default:
                return null;
        }
    }

    private function getAssignedName(string $moduleKey, $row): string
    {
        $userId = match ($moduleKey) {
            'lead_submissions' => $row->executive_id ?? $row->sales_agent_id,
            'field_submissions' => $row->field_executive_id ?? $row->sales_agent_id,
            'customer_support_requests' => $row->sales_agent_id ?? $row->created_by,
            'vas_requests' => $row->back_office_executive_id ?? $row->sales_agent_id,
            default => null,
        };
        if (!$userId) return '—';
        static $nameCache = [];
        if (!isset($nameCache[$userId])) {
            $nameCache[$userId] = \App\Models\User::where('id', $userId)->value('name') ?? '—';
        }
        return $nameCache[$userId];
    }

    private function formatRequestId(string $moduleKey, int $id): string
    {
        $prefix = match ($moduleKey) {
            'lead_submissions' => 'LD',
            'field_submissions' => 'FD',
            'customer_support_requests' => 'CS',
            'vas_requests' => 'VAS',
            default => 'REQ',
        };
        return $prefix . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    }

    private function minutesToHuman(int $minutes): string
    {
        if ($minutes <= 0) return '0 hours';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) return "{$h}.{$m} hours";
        if ($h > 0) return "{$h} hours";
        return "{$m} min";
    }

    private function generateInsights(array $departments, array $priority, array $kpis, array $monthlyTrend): array
    {
        $insights = [];

        $sorted = collect($departments)->sortByDesc('compliance_pct');
        $best = $sorted->first();
        $worst = $sorted->last();

        if ($best && $best['compliance_pct'] >= 85) {
            $insights[] = [
                'type'  => 'positive',
                'title' => 'Strong Performance',
                'text'  => "{$best['name']} maintains {$best['compliance_pct']}% compliance rate with excellent response times.",
            ];
        }

        if ($worst && $worst['compliance_pct'] < 85 && count($departments) > 1) {
            $insights[] = [
                'type'  => 'warning',
                'title' => 'Needs Attention',
                'text'  => "{$worst['name']} showing {$worst['compliance_pct']}% compliance — requires process review.",
            ];
        }

        $highPriority = collect($priority)->firstWhere('level', 'High');
        if ($highPriority) {
            $insights[] = [
                'type'  => 'info',
                'title' => 'High Priority Focus',
                'text'  => "High priority requests have {$highPriority['compliance_pct']}% compliance — consider resource allocation.",
            ];
        }

        if (count($monthlyTrend) >= 2) {
            $last = end($monthlyTrend);
            $prev = prev($monthlyTrend);
            $diff = round($last['compliance_pct'] - $prev['compliance_pct'], 1);
            if ($diff >= 0) {
                $insights[] = [
                    'type'  => 'positive',
                    'title' => 'Positive Trend',
                    'text'  => "Overall compliance improved by {$diff}% compared to previous period.",
                ];
            } else {
                $insights[] = [
                    'type'  => 'warning',
                    'title' => 'Declining Trend',
                    'text'  => 'Overall compliance dropped by ' . abs($diff) . '% compared to previous period.',
                ];
            }
        }

        return $insights;
    }

    /**
     * GET /api/reports/support-stats
     * KPIs for Customer Support: total, by_status, by_csr, monthly trend, avg resolution time.
     */
    public function supportStats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSupportSubmission::class);

        $validated = $request->validate([
            'from'       => ['sometimes', 'nullable', 'date'],
            'to'         => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'status'     => ['sometimes', 'nullable', 'string'],
            'csr_id'     => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $query = CustomerSupportSubmission::query()->visibleTo($request->user());
        if (! empty($validated['from'])) $query->where('created_at', '>=', $validated['from'] . ' 00:00:00');
        if (! empty($validated['to']))   $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        if (! empty($validated['status'])) $query->where('status', $validated['status']);
        if (! empty($validated['csr_id'])) $query->where('csr_id', $validated['csr_id']);

        $total = (clone $query)->count();
        $open  = (clone $query)->whereIn('status', ['open', 'submitted'])->count();
        $resolved = (clone $query)->whereIn('status', ['resolved', 'closed', 'completed'])->count();

        $byStatus = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')->pluck('count', 'status')->toArray();

        $byCsr = (clone $query)
            ->leftJoin('users', 'customer_support_submissions.csr_id', '=', 'users.id')
            ->select('users.name', DB::raw('count(customer_support_submissions.id) as count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('count')->limit(10)
            ->get()
            ->map(fn ($r) => ['name' => $r->name ?? 'Unassigned', 'count' => (int) $r->count])
            ->toArray();

        $monthlyTrend = (clone $query)
            ->select(DB::raw('YEAR(created_at) as year'), DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as count'))
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'label' => date('M Y', mktime(0, 0, 0, (int) $r->month, 1, (int) $r->year)),
                'count' => (int) $r->count,
            ])->toArray();

        return response()->json([
            'total_tickets'    => $total,
            'open_tickets'     => $open,
            'resolved_tickets' => $resolved,
            'resolution_rate'  => $total > 0 ? round(100 * $resolved / $total, 1) : 0,
            'by_status'        => $byStatus,
            'by_csr'           => $byCsr,
            'monthly_trend'    => $monthlyTrend,
        ]);
    }

    /**
     * GET /api/reports/client-stats
     * Client & Company reports: total, by_status, top clients by submissions, revenue overview.
     */
    public function clientStats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $validated = $request->validate([
            'from'   => ['sometimes', 'nullable', 'date'],
            'to'     => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'status' => ['sometimes', 'nullable', 'string'],
        ]);

        $query = Client::query();
        if (! empty($validated['from'])) $query->where('clients.created_at', '>=', $validated['from'] . ' 00:00:00');
        if (! empty($validated['to']))   $query->where('clients.created_at', '<=', $validated['to'] . ' 23:59:59');
        if (! empty($validated['status'])) $query->where('clients.status', $validated['status']);

        $total  = (clone $query)->count();

        $byStatus = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')->pluck('count', 'status')->toArray();

        // Top 10 clients by submission count (across all modules)
        $topClients = Client::select('clients.id', 'clients.company_name', 'clients.account_number')
            ->selectRaw('(SELECT COUNT(*) FROM lead_submissions WHERE lead_submissions.client_id = clients.id) +
                         (SELECT COUNT(*) FROM field_submissions WHERE field_submissions.client_id = clients.id) +
                         (SELECT COUNT(*) FROM customer_support_submissions WHERE customer_support_submissions.client_id = clients.id) +
                         (SELECT COUNT(*) FROM vas_request_submissions WHERE vas_request_submissions.client_id = clients.id) as total_submissions')
            ->orderByDesc('total_submissions')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'company_name'     => $c->company_name,
                'account_number'   => $c->account_number,
                'total_submissions' => (int) $c->total_submissions,
            ])->toArray();

        // Monthly client growth
        $monthlyGrowth = Client::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('count(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'label' => date('M Y', mktime(0, 0, 0, (int) $r->month, 1, (int) $r->year)),
                'count' => (int) $r->count,
            ])->toArray();

        // Revenue summary (MRC from Lead Submissions)
        $revenueSummary = LeadSubmission::query()
            ->whereNotNull('mrc_aed')
            ->where('mrc_aed', '>', 0)
            ->select(
                DB::raw('SUM(mrc_aed) as total_mrc'),
                DB::raw('AVG(mrc_aed) as avg_mrc'),
                DB::raw('COUNT(*) as deals_with_mrc')
            )
            ->first();

        return response()->json([
            'total_clients'     => $total,
            'by_status'         => $byStatus,
            'top_clients'       => $topClients,
            'monthly_growth'    => $monthlyGrowth,
            'revenue_summary'   => [
                'total_mrc'      => round((float) ($revenueSummary->total_mrc ?? 0), 2),
                'avg_mrc'        => round((float) ($revenueSummary->avg_mrc ?? 0), 2),
                'deals_with_mrc' => (int) ($revenueSummary->deals_with_mrc ?? 0),
            ],
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
        if (! empty($validated['back_office_executive_id'])) {
            $query->where('back_office_executive_id', $validated['back_office_executive_id']);
        }
        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'like', $term)
                    ->orWhere('account_number', 'like', $term)
                    ->orWhere('request_description', 'like', $term);
            });
        }
    }
}
