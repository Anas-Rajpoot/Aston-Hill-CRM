<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    /**
     * Check if user can view all logs (super admin or has permission).
     */
    private function canViewAll($user): bool
    {
        return $user && ($user->hasRole('superadmin') || $user->can('notification_rules.view_logs'));
    }

    /**
     * GET /api/notification-logs
     *
     * - Super admin / users with 'notification_rules.view_logs': see latest 10 of ALL logs
     * - Other users: see latest 10 logs related to their email only
     *
     * Supports: per_page (default 10), page, trigger, channel, status, date_from, date_to, q
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = NotificationLog::query()->orderByDesc('created_at');

        // Non-admin users: only show logs where sent_to matches their email
        if (! $this->canViewAll($user)) {
            $query->where('sent_to', $user->email);
        }

        // Filters
        if ($trigger = $request->input('trigger')) {
            $query->where('trigger_key', $trigger);
        }
        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('sent_to', 'like', "%{$q}%")
                    ->orWhere('module', 'like', "%{$q}%");
            });
        }

        // Default to 10 per page (latest 10)
        $perPage   = min((int) $request->input('per_page', 10), 100);
        $paginated = $query->paginate($perPage);

        return response()->json($paginated);
    }
}
