<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\SystemAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    private function enrichRow(NotificationLog $row): NotificationLog
    {
        $payload = is_array($row->payload) ? $row->payload : [];
        $type = $row->trigger_key ?: 'notification';
        $subject = $payload['title']
            ?? ($row->module ? ($row->module . ' Notification') : 'Notification');
        $message = $payload['message']
            ?? ($row->module
                ? sprintf('%s event: %s.', $row->module, str_replace('_', ' ', (string) $type))
                : str_replace('_', ' ', (string) $type));

        $row->setAttribute('type', $type);
        $row->setAttribute('subject', $subject);
        $row->setAttribute('message', $message);
        $row->setAttribute('body', $message);

        return $row;
    }

    private function dedupeKey(NotificationLog $row): ?string
    {
        $payload = is_array($row->payload) ? $row->payload : [];
        $message = strtolower((string) ($row->getAttribute('message') ?? ''));
        $subject = strtolower((string) ($row->getAttribute('subject') ?? ''));
        $module = strtolower((string) ($row->module ?? ''));
        $trigger = strtolower((string) ($row->trigger_key ?? ''));

        // Deduplicate repeated event rows shown to super-admin/all-users view.
        // This collapses per-recipient log rows into one event row.
        $isEventRow = in_array($trigger, ['alert_generated', 'new_submission_created', 'sla_breached'], true)
            || $module === 'client alerts'
            || ($trigger === 'status_updated' && str_contains($message, 'alert "'));

        if (! $isEventRow) {
            return null;
        }

        $alertId = (int) ($payload['alert_id'] ?? 0);
        if ($alertId > 0) {
            return 'alert_id:' . $alertId;
        }

        $submissionId = (int) ($payload['submission_id'] ?? 0);
        if ($submissionId > 0) {
            return 'submission_id:' . $submissionId;
        }

        $recordId = (int) ($payload['record_id'] ?? 0);
        if ($recordId > 0) {
            return 'record_id:' . $recordId;
        }

        // Generic fallback: same trigger + same message is treated as one event.
        return 'event_msg:' . md5($trigger . '|' . $subject . '|' . $message);
    }

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
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);

        $canViewAll = $this->canViewAll($user);

        // Non-admin users: only show logs where sent_to matches their email
        if (! $canViewAll) {
            $query->where(function ($q) use ($user) {
                $hasAny = false;
                if (! empty($user->email)) {
                    $q->where('sent_to', $user->email);
                    $hasAny = true;
                }
                if (! empty($user->name)) {
                    if ($hasAny) {
                        $q->orWhere('sent_to', $user->name);
                    } else {
                        $q->where('sent_to', $user->name);
                    }
                    $hasAny = true;
                }
                if (! $hasAny) {
                    $q->whereRaw('1 = 0');
                }
            });
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

        // Build latest notification feed with dedupe (for repeated per-recipient rows).
        $rows = $query->limit(2000)->get()->map(fn (NotificationLog $row) => $this->enrichRow($row));
        $seen = [];
        $deduped = collect();
        foreach ($rows as $row) {
            $key = $this->dedupeKey($row);
            if ($key !== null) {
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $deduped->push($row);
        }

        $total = $deduped->count();
        $lastPage = max(1, (int) ceil(max(1, $total) / max(1, $perPage)));
        $page = min(max(1, $page), $lastPage);
        $items = $deduped->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $items->all(),
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'meta' => [
                'can_view_all'    => $canViewAll,
                'can_delete_logs' => $this->canDeleteLogs($user),
                'viewing_scope'   => $canViewAll ? 'all' : 'personal',
            ],
        ]);
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
