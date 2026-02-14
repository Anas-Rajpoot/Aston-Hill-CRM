<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\SystemAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    /**
     * Super admin check.
     */
    private function isSuperAdmin($user): bool
    {
        return $user && $user->hasRole('superadmin');
    }

    /**
     * Check if user can view ALL logs (super admin or has view_logs permission).
     * Other users see only their own logs.
     */
    private function canViewAll($user): bool
    {
        return $this->isSuperAdmin($user)
            || ($user && ($user->can('notification_rules.view_logs') || $user->can('manage-notification-rules')));
    }

    /**
     * Check if user can delete logs.
     */
    private function canDeleteLogs($user): bool
    {
        return $this->isSuperAdmin($user)
            || ($user && ($user->can('notification_rules.delete_logs') || $user->can('manage-notification-rules')));
    }

    /**
     * GET /api/notification-logs
     *
     * - Super admin / users with 'notification_rules.view_logs': see latest 10 of ALL logs
     * - Other users: see latest 10 logs related to their email only
     *
     * Fixed at 10 records to show the most recent notifications.
     * Supports filters: trigger, channel, status, date_from, date_to, q
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = NotificationLog::query()->orderByDesc('created_at');

        $canViewAll = $this->canViewAll($user);

        // Non-admin users: only show logs where sent_to matches their email
        if (! $canViewAll) {
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

        // Fixed at 10 latest records
        $perPage   = 10;
        $paginated = $query->paginate($perPage);

        return response()->json(array_merge($paginated->toArray(), [
            'meta' => [
                'can_view_all'    => $canViewAll,
                'can_delete_logs' => $this->canDeleteLogs($user),
                'viewing_scope'   => $canViewAll ? 'all' : 'personal',
            ],
        ]));
    }

    /**
     * DELETE /api/notification-logs/{notificationLog}
     *
     * Only super admin or users with 'notification_rules.delete_logs' can delete.
     */
    public function destroy(Request $request, NotificationLog $notificationLog): JsonResponse
    {
        $user = $request->user();

        if (! $this->canDeleteLogs($user)) {
            return response()->json(['message' => 'Unauthorized. You need "Delete Notification Logs" permission.'], 403);
        }

        $old = $notificationLog->toArray();
        $notificationLog->delete();

        // Audit log the deletion
        SystemAuditLog::record(
            'notification_log.deleted',
            $old,
            [],
            $user->id,
            'notification_log',
            $old['id'],
        );

        return response()->json(['message' => 'Notification log entry deleted.']);
    }
}
