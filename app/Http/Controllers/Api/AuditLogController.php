<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /* ── GET /api/audit-logs ────────────────────────────── */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->betweenDates($request->input('date_from'), $request->input('date_to'))
            ->ofUser($request->input('user_name'))
            ->ofRole($request->input('user_role'))
            ->ofModule($request->input('module'))
            ->ofAction($request->input('action'))
            ->ofResult($request->input('result'))
            ->ofIp($request->input('ip'))
            ->ofSession($request->input('session_id'))
            ->ofDevice($request->input('device'))
            ->search($request->input('q'));

        // Sort
        $sortField = 'occurred_at';
        $sortDir   = 'desc';
        if ($sort = $request->input('sort')) {
            $parts   = explode(':', $sort);
            $allowed = ['occurred_at', 'user_name', 'user_role', 'action', 'module', 'record_ref', 'ip', 'device', 'result'];
            $sortField = in_array($parts[0], $allowed) ? $parts[0] : 'occurred_at';
            $sortDir   = ($parts[1] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        }
        $query->orderBy($sortField, $sortDir);

        $perPage   = min((int) $request->input('per_page', 10), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => AuditLogResource::collection($paginated->items()),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
        ]);
    }

    /* ── GET /api/audit-logs/stats ──────────────────────── */
    public function stats(): JsonResponse
    {
        $data = Cache::remember('audit_logs_stats', 300, function () {
            $total   = AuditLog::count();
            $success = AuditLog::where('result', 'success')->count();
            $failed  = AuditLog::where('result', 'failure')->count();
            $active  = AuditLog::where('occurred_at', '>=', now()->subHours(24))
                           ->distinct('user_name')
                           ->count('user_name');

            return [
                'total'         => $total,
                'success_rate'  => $total > 0 ? round(($success / $total) * 100) : 0,
                'failed'        => $failed,
                'active_users'  => $active,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /* ── GET /api/audit-logs/{id} ───────────────────────── */
    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json(['data' => new AuditLogResource($auditLog)]);
    }

    /* ── GET /api/audit-logs/export ─────────────────────── */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user || ! ($user->hasRole('superadmin') || $user->can('export-audit-logs'))) {
            abort(403, 'You do not have permission to export audit logs.');
        }

        $query = AuditLog::query()
            ->betweenDates($request->input('date_from'), $request->input('date_to'))
            ->ofUser($request->input('user_name'))
            ->ofRole($request->input('user_role'))
            ->ofModule($request->input('module'))
            ->ofAction($request->input('action'))
            ->ofResult($request->input('result'))
            ->ofIp($request->input('ip'))
            ->orderBy('occurred_at', 'desc')
            ->limit(50000);

        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date/Time', 'User', 'Role', 'Action', 'Module', 'Record ID', 'IP', 'Device', 'Status']);

            $query->cursor()->each(function ($log) use ($out) {
                fputcsv($out, [
                    $log->occurred_at?->format('Y-m-d H:i:s'),
                    $log->user_name,
                    $log->user_role,
                    $log->action,
                    $log->module,
                    $log->record_ref ?? $log->record_id,
                    $log->ip,
                    $log->device,
                    $log->result,
                ]);
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /* ── GET /api/audit-logs/meta ───────────────────────── */
    public function meta(): JsonResponse
    {
        $data = Cache::remember('audit_logs_meta', 600, function () {
            return [
                'actions' => AuditLog::distinct()->pluck('action')->sort()->values(),
                'modules' => AuditLog::distinct()->pluck('module')->sort()->values(),
                'roles'   => AuditLog::distinct()->whereNotNull('user_role')->pluck('user_role')->sort()->values(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
