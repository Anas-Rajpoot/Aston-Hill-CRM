<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationEscalation;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\NotificationTrigger;
use App\Models\SystemAuditLog;
use App\Models\UserNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationConfigController extends Controller
{
    /**
     * Check if user is super admin.
     */
    private function isSuperAdmin($user): bool
    {
        return $user && $user->hasRole('superadmin');
    }

    /**
     * Check if user can manage any notification settings (legacy + granular).
     */
    private function canManage($user): bool
    {
        return $this->isSuperAdmin($user) || ($user && $user->can('manage-notification-rules'));
    }

    /**
     * Check if user can view the notifications page at all.
     */
    private function canView($user): bool
    {
        return $this->isSuperAdmin($user)
            || ($user && (
                $user->can('notification_rules.list')
                || $user->can('notification_rules.view')
                || $user->can('manage-notification-rules')
            ));
    }

    /**
     * Check specific granular permission (falls back to canManage for super admin).
     */
    private function canDo($user, string $permission): bool
    {
        return $this->isSuperAdmin($user) || ($user && (
            $user->can($permission) || $user->can('manage-notification-rules')
        ));
    }

    // ──────────────────────────────────────────────────────────
    //  GET /api/notification-config — aggregated config + permissions
    // ──────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Any authenticated user can view (with limited data for non-admins)
        // But only users with view permission see the full config
        if (! $this->canView($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Settings + escalations are global (cached)
        $global = Cache::remember(NotificationSetting::CACHE_KEY, NotificationSetting::CACHE_TTL, function () {
            return [
                'settings'    => NotificationSetting::singleton(),
                'escalations' => NotificationEscalation::orderBy('level')->get(),
            ];
        });

        // Triggers are resolved per-user (channel lock + user prefs + defaults)
        $triggers = NotificationTrigger::allWithResolvedState($user->id);

        // Channel states for the frontend
        $s = $global['settings'];
        $channels = [
            'enable_email'      => (bool) $s->enable_email,
            'enable_web'        => (bool) $s->enable_web,
            'enable_sla_alerts' => (bool) $s->enable_sla_alerts,
        ];

        // Granular permissions for the frontend to conditionally show/hide sections
        $permissions = [
            'can_update'           => $this->canManage($user),
            'can_edit_settings'    => $this->canDo($user, 'notification_rules.edit_settings'),
            'can_manage_channels'  => $this->canDo($user, 'notification_rules.manage_channels'),
            'can_manage_triggers'  => $this->canDo($user, 'notification_rules.manage_triggers'),
            'can_manage_escalations' => $this->canDo($user, 'notification_rules.manage_escalations'),
            'can_manage_templates' => $this->canDo($user, 'notification_rules.manage_templates'),
            'can_view_logs'        => $this->canDo($user, 'notification_rules.view_logs'),
            'can_send_test'        => $this->canDo($user, 'notification_rules.send_test'),
            'can_delete'           => $this->canDo($user, 'notification_rules.delete'),
        ];

        return response()->json([
            'data' => [
                'settings'    => $global['settings'],
                'triggers'    => $triggers,
                'escalations' => $global['escalations'],
                'channels'    => $channels,
            ],
            'meta' => $permissions,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  PUT /api/notification-settings — update global settings
    //  Accepts both full form saves and individual channel toggles.
    // ──────────────────────────────────────────────────────────
    public function updateSettings(Request $request): JsonResponse
    {
        if (! $this->canDo($request->user(), 'notification_rules.edit_settings')) {
            return response()->json(['message' => 'Unauthorized. You need "Edit Global Email Settings" permission.'], 403);
        }

        $validated = $request->validate([
            'default_sender_email' => 'sometimes|required|email|max:255',
            'cc_emails'            => 'sometimes|nullable|string|max:2000',
            'bcc_emails'           => 'sometimes|nullable|string|max:2000',
            'enable_email'         => 'sometimes|boolean',
            'enable_web'           => 'sometimes|boolean',
            'enable_sms'           => 'sometimes|boolean',
            'enable_sla_alerts'    => 'sometimes|boolean',
        ]);

        // Parse comma-separated email lists → arrays with per-email validation
        foreach (['cc_emails', 'bcc_emails'] as $field) {
            if (isset($validated[$field]) && is_string($validated[$field])) {
                $parsed = array_values(array_filter(array_map('trim', explode(',', $validated[$field]))));
                foreach ($parsed as $email) {
                    if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        return response()->json([
                            'message' => "Invalid email in {$field}: {$email}",
                            'errors'  => [$field => ["Contains invalid email: {$email}"]],
                        ], 422);
                    }
                }
                $validated[$field] = $parsed;
            }
        }

        $settings  = NotificationSetting::singleton();
        $oldValues = $settings->only(array_keys($validated));

        $settings->fill($validated);
        $settings->updated_by = $request->user()->id;
        $settings->save();

        NotificationSetting::clearConfigCache();
        \App\Services\NotificationService::clearCache();

        // Audit — only changed fields
        $newValues = $settings->only(array_keys($oldValues));
        $changed = array_filter(
            $oldValues,
            fn ($v, $k) => json_encode($v) !== json_encode($newValues[$k] ?? null),
            ARRAY_FILTER_USE_BOTH
        );
        if (! empty($changed)) {
            SystemAuditLog::record(
                'notification_settings.updated',
                $changed,
                array_intersect_key($newValues, $changed),
                $request->user()->id,
                'notification_setting',
                $settings->id,
            );
        }

        return response()->json(['message' => 'Settings updated.', 'data' => $settings->fresh()]);
    }

    // ──────────────────────────────────────────────────────────
    //  PATCH /api/notification-triggers/{trigger} — toggle system-wide defaults (super admin)
    //  Enforces channel hierarchy: returns 403 if channel is OFF.
    // ──────────────────────────────────────────────────────────
    public function updateTrigger(Request $request, NotificationTrigger $trigger): JsonResponse
    {
        if (! $this->canDo($request->user(), 'notification_rules.manage_triggers')) {
            return response()->json(['message' => 'Unauthorized. You need "Manage Notification Triggers" permission.'], 403);
        }

        $fields = ['website_enabled', 'email_enabled', 'in_app_enabled', 'email_alert_enabled', 'is_active'];
        $rules  = [];
        foreach ($fields as $f) {
            if ($request->has($f)) {
                $rules[$f] = 'boolean';
            }
        }
        if (empty($rules)) {
            return response()->json(['message' => 'No fields to update.'], 422);
        }

        $validated = $request->validate($rules);

        // ── Enforce channel hierarchy ────────────────────────
        // Cannot enable a trigger column if its parent channel is OFF.
        foreach ($validated as $col => $value) {
            if (! $value) continue; // disabling is always allowed

            $channel = NotificationTrigger::COLUMN_TO_CHANNEL[$col] ?? null;
            if ($channel && ! NotificationTrigger::isChannelEnabled($channel)) {
                $label = str_replace('_', ' ', $channel);
                return response()->json([
                    'message' => "Cannot enable trigger: the \"{$label}\" channel is disabled at the system level.",
                ], 403);
            }
        }

        $oldValues = $trigger->only(array_keys($validated));

        $trigger->fill($validated);
        $trigger->updated_by = $request->user()->id;
        $trigger->save();

        NotificationSetting::clearConfigCache();

        $changed = array_filter(
            $oldValues,
            fn ($v, $k) => $v !== ($validated[$k] ?? null),
            ARRAY_FILTER_USE_BOTH
        );
        if (! empty($changed)) {
            SystemAuditLog::record(
                'notification_trigger.updated',
                $changed,
                array_intersect_key($validated, $changed),
                $request->user()->id,
                'notification_trigger',
                $trigger->id,
            );
        }

        return response()->json(['message' => 'Trigger updated.', 'data' => $trigger->fresh()]);
    }

    // ──────────────────────────────────────────────────────────
    //  PUT /api/notification-triggers/{trigger}/{channel} — per-user preference
    //  Any authenticated user can toggle their own trigger preferences,
    //  but ONLY if the parent channel is ON.
    // ──────────────────────────────────────────────────────────
    public function updateUserTriggerPreference(Request $request, NotificationTrigger $trigger, string $channel): JsonResponse
    {
        $allowedChannels = ['email', 'in_app', 'sla_alerts'];
        if (! in_array($channel, $allowedChannels)) {
            return response()->json(['message' => 'Invalid channel.'], 422);
        }

        $validated = $request->validate(['enabled' => 'required|boolean']);

        // ── Enforce channel hierarchy ────────────────────────
        if ($validated['enabled'] && ! NotificationTrigger::isChannelEnabled($channel)) {
            $label = str_replace('_', ' ', $channel);
            return response()->json([
                'message' => "Cannot enable trigger: the \"{$label}\" channel is disabled at the system level.",
            ], 403);
        }

        $user = $request->user();
        $pref = UserNotificationPreference::setPreference(
            $user->id,
            $trigger->id,
            $channel,
            $validated['enabled'],
        );

        SystemAuditLog::record(
            'user_notification_preference.updated',
            ['channel' => $channel, 'trigger' => $trigger->key, 'old_enabled' => ! $validated['enabled']],
            ['channel' => $channel, 'trigger' => $trigger->key, 'new_enabled' => $validated['enabled']],
            $user->id,
            'user_notification_preference',
            $pref->id,
        );

        return response()->json([
            'message' => 'Preference saved.',
            'data'    => $pref,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  POST /api/notification-triggers/{channel}/reset — reset all user prefs for channel
    //  Clears per-user overrides so triggers fall back to system defaults.
    // ──────────────────────────────────────────────────────────
    public function resetChannelPreferences(Request $request, string $channel): JsonResponse
    {
        $allowedChannels = ['email', 'in_app', 'sla_alerts'];
        if (! in_array($channel, $allowedChannels)) {
            return response()->json(['message' => 'Invalid channel.'], 422);
        }

        $user  = $request->user();
        $count = UserNotificationPreference::resetChannel($user->id, $channel);

        if ($count > 0) {
            SystemAuditLog::record(
                'user_notification_preferences.reset',
                ['channel' => $channel, 'cleared_count' => $count],
                ['channel' => $channel, 'reset_to' => 'system_defaults'],
                $user->id,
                'user_notification_preference',
                0,
            );
        }

        return response()->json([
            'message' => "Preferences reset to defaults for {$channel} channel.",
            'cleared' => $count,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  PUT /api/notification-escalations — upsert all levels
    // ──────────────────────────────────────────────────────────
    public function upsertEscalations(Request $request): JsonResponse
    {
        if (! $this->canDo($request->user(), 'notification_rules.manage_escalations')) {
            return response()->json(['message' => 'Unauthorized. You need "Manage Escalation Levels" permission.'], 403);
        }

        $request->validate([
            'levels'             => 'required|array|min:1|max:10',
            'levels.*.level'     => 'required|integer|min:1',
            'levels.*.to_emails' => 'required|string|max:2000',
            'levels.*.enabled'   => 'sometimes|boolean',
        ]);

        $userId = $request->user()->id;
        $old    = NotificationEscalation::orderBy('level')->get()->toArray();

        DB::transaction(function () use ($request, $userId) {
            $incomingLevels = collect($request->input('levels'))->pluck('level')->toArray();
            NotificationEscalation::whereNotIn('level', $incomingLevels)->delete();

            foreach ($request->input('levels') as $row) {
                $emails = array_values(array_filter(array_map('trim', explode(',', $row['to_emails']))));
                NotificationEscalation::updateOrCreate(
                    ['level' => $row['level']],
                    ['to_emails' => $emails, 'enabled' => $row['enabled'] ?? true, 'updated_by' => $userId]
                );
            }
        });

        NotificationSetting::clearConfigCache();

        SystemAuditLog::record(
            'notification_escalations.updated',
            ['levels' => $old],
            ['levels' => NotificationEscalation::orderBy('level')->get()->toArray()],
            $userId,
            'notification_escalation',
            null,
        );

        return response()->json([
            'message' => 'Escalation levels saved.',
            'data'    => NotificationEscalation::orderBy('level')->get(),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  POST /api/notification-test — send test notification
    // ──────────────────────────────────────────────────────────
    public function testNotification(Request $request): JsonResponse
    {
        if (! $this->canDo($request->user(), 'notification_rules.send_test')) {
            return response()->json(['message' => 'Unauthorized. You need "Send Test Notification" permission.'], 403);
        }

        $request->validate([
            'trigger_key' => 'required|exists:notification_triggers,key',
            'email'       => 'required|email|max:255',
        ]);

        // Check if email channel is enabled
        if (! \App\Services\NotificationService::isEmailEnabled()) {
            return response()->json([
                'message' => 'Email notifications are currently disabled. Enable "Email Notifications" in Notification Channels first.',
            ], 422);
        }

        $trigger  = NotificationTrigger::where('key', $request->input('trigger_key'))->first();

        // If this is an SLA trigger, also check SLA alerts toggle
        $triggerKey = $request->input('trigger_key');
        if (\App\Services\NotificationService::isSlaRelated($triggerKey)
            && ! \App\Services\NotificationService::isSlaAlertsEnabled()) {
            return response()->json([
                'message' => 'SLA alerts are currently disabled. Enable "SLA Alerts" in Notification Channels first.',
            ], 422);
        }

        $emailCfg = NotificationSetting::emailConfig();
        $to       = $request->input('email');
        $status   = 'sent';
        $error    = null;

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test notification for trigger: ' . ($trigger->name ?? $triggerKey),
                function ($message) use ($emailCfg, $to, $trigger) {
                    $message->from($emailCfg['from'], config('app.name', 'Aston Hill CRM'))
                            ->to($to)
                            ->subject('Test Notification — ' . ($trigger->name ?? 'System'));

                    if (! empty($emailCfg['cc'])) {
                        $message->cc($emailCfg['cc']);
                    }
                    if (! empty($emailCfg['bcc'])) {
                        $message->bcc($emailCfg['bcc']);
                    }
                }
            );
        } catch (\Throwable $e) {
            $status = 'failed';
            $error  = $e->getMessage();
        }

        NotificationLog::create([
            'trigger_key' => $triggerKey,
            'channel'     => 'email',
            'module'      => 'Test',
            'sent_to'     => $to,
            'status'      => $status,
            'error'       => $error,
            'payload'     => [
                'test'         => true,
                'trigger_name' => $trigger->name ?? '',
                'from'         => $emailCfg['from'],
                'cc'           => $emailCfg['cc'],
                'bcc'          => $emailCfg['bcc'],
            ],
        ]);

        if ($status === 'failed') {
            return response()->json(['message' => 'Test notification failed: ' . $error], 500);
        }

        return response()->json(['message' => 'Test notification sent to ' . $to . ' from ' . $emailCfg['from'] . '.']);
    }
}
