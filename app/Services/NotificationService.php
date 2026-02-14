<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\NotificationTrigger;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Central notification dispatcher that respects the three master channel toggles:
 *
 *  1) enable_email      – gate all email sending
 *  2) enable_web        – gate in-app / bell notifications
 *  3) enable_sla_alerts – gate SLA-specific alerts INDEPENDENTLY
 *
 * SLA alerts are treated as a separate priority toggle. Even if email/web
 * channels are on, SLA notifications are only dispatched when enable_sla_alerts
 * is also on. Conversely, if SLA alerts are on but email is off, SLA emails
 * will NOT be sent (both the channel and SLA toggle must be on).
 *
 * Usage:
 *   NotificationService::dispatch('sla_approaching_breach', [
 *       'module'  => 'leads',
 *       'title'   => 'SLA Warning: Lead #123',
 *       'message' => 'Lead submission is approaching SLA breach.',
 *       'url'     => '/leads/123',
 *       'users'   => User::where(...)->get(),          // recipients
 *       'email_to'=> ['ops@astonhill.ae'],              // explicit email addresses
 *   ]);
 */
class NotificationService
{
    // ──────────────────────────────────────────────────────────
    //  Cached channel config (master toggles)
    // ──────────────────────────────────────────────────────────
    public static function channels(): array
    {
        return Cache::remember('notif_channels', 300, function () {
            $s = NotificationSetting::singleton();
            return [
                'email'      => (bool) $s->enable_email,
                'web'        => (bool) $s->enable_web,
                'sla_alerts' => (bool) $s->enable_sla_alerts,
            ];
        });
    }

    public static function isEmailEnabled(): bool    { return self::channels()['email']; }
    public static function isWebEnabled(): bool      { return self::channels()['web']; }
    public static function isSlaAlertsEnabled(): bool { return self::channels()['sla_alerts']; }

    /**
     * Check whether a specific trigger_key is an SLA-related trigger.
     */
    public static function isSlaRelated(string $triggerKey): bool
    {
        return str_contains($triggerKey, 'sla_');
    }

    // ──────────────────────────────────────────────────────────
    //  Main dispatch
    // ──────────────────────────────────────────────────────────
    /**
     * @param string $triggerKey  e.g. 'new_submission_created', 'sla_approaching_breach'
     * @param array  $payload     [title, message, module, url, users (Collection|array), email_to (array)]
     */
    public static function dispatch(string $triggerKey, array $payload = []): void
    {
        try {
            $channels = self::channels();
            $trigger  = NotificationTrigger::where('key', $triggerKey)->first();

            // If trigger is inactive, skip entirely
            if ($trigger && ! $trigger->is_active) {
                return;
            }

            $isSla = self::isSlaRelated($triggerKey);

            // ── SLA gate: if this is SLA-related and SLA alerts are off → skip ──
            if ($isSla && ! $channels['sla_alerts']) {
                self::log($triggerKey, 'all', $payload['module'] ?? null, '—', 'skipped', 'SLA alerts are disabled');
                return;
            }

            // ── Send email ──────────────────────────────────────────
            // Channel must be ON AND trigger system default must allow email
            $shouldEmail = $channels['email']
                && ($trigger ? $trigger->email_enabled : true);

            if ($shouldEmail) {
                self::sendEmail($triggerKey, $payload);
            }

            // ── Send in-app (bell) notification ─────────────────────
            // Channel must be ON AND trigger system default must allow in-app
            // Additionally, filter recipients by their per-user preferences
            $shouldWeb = $channels['web']
                && ($trigger ? $trigger->in_app_enabled : true);

            if ($shouldWeb) {
                self::sendInApp($triggerKey, $payload, $trigger);
            }

        } catch (\Throwable $e) {
            Log::error('NotificationService::dispatch failed', [
                'trigger' => $triggerKey,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve whether a notification should be sent to a specific user for a
     * given trigger + channel. Respects the per-user preference hierarchy:
     *   1) Channel OFF → false
     *   2) User pref exists → use it
     *   3) Fallback → trigger default
     */
    public static function isEnabledForUser(int $userId, string $triggerKey, string $channel): bool
    {
        // 1) Master channel must be on
        if (! NotificationTrigger::isChannelEnabled($channel)) {
            return false;
        }

        $trigger = NotificationTrigger::where('key', $triggerKey)->first();
        if (! $trigger || ! $trigger->is_active) {
            return false;
        }

        // 2) Check user preference
        $userPrefs = UserNotificationPreference::forUser($userId);
        $prefKey   = $trigger->id . '_' . $channel;

        if (isset($userPrefs[$prefKey])) {
            return (bool) $userPrefs[$prefKey];
        }

        // 3) Fallback to trigger system default
        $defaultCol = NotificationTrigger::CHANNEL_TO_DEFAULT_COL[$channel] ?? null;
        return $defaultCol ? (bool) $trigger->{$defaultCol} : true;
    }

    // ──────────────────────────────────────────────────────────
    //  Email channel
    // ──────────────────────────────────────────────────────────
    protected static function sendEmail(string $triggerKey, array $payload): void
    {
        $emailCfg   = NotificationSetting::emailConfig();
        $recipients = $payload['email_to'] ?? [];
        $title      = $payload['title'] ?? 'Notification';
        $message    = $payload['message'] ?? '';
        $module     = $payload['module'] ?? null;

        if (empty($recipients)) {
            return;
        }

        foreach ($recipients as $to) {
            $status = 'sent';
            $error  = null;

            try {
                Mail::raw($message, function ($mail) use ($emailCfg, $to, $title) {
                    $mail->from($emailCfg['from'], config('app.name', 'Aston Hill CRM'))
                         ->to($to)
                         ->subject($title);

                    if (! empty($emailCfg['cc'])) {
                        $mail->cc($emailCfg['cc']);
                    }
                    if (! empty($emailCfg['bcc'])) {
                        $mail->bcc($emailCfg['bcc']);
                    }
                });
            } catch (\Throwable $e) {
                $status = 'failed';
                $error  = $e->getMessage();
            }

            self::log($triggerKey, 'email', $module, $to, $status, $error);
        }
    }

    // ──────────────────────────────────────────────────────────
    //  In-app (bell) channel — uses Laravel's notifications table
    // ──────────────────────────────────────────────────────────
    protected static function sendInApp(string $triggerKey, array $payload, ?NotificationTrigger $trigger = null): void
    {
        $users   = $payload['users'] ?? collect();
        $title   = $payload['title'] ?? 'Notification';
        $message = $payload['message'] ?? '';
        $url     = $payload['url'] ?? null;
        $module  = $payload['module'] ?? null;
        $isSla   = self::isSlaRelated($triggerKey);

        if ($users instanceof \Illuminate\Support\Collection) {
            $users = $users->all();
        }

        if (empty($users)) {
            return;
        }

        foreach ($users as $user) {
            // ── Per-user preference check ───────────────────────
            // Skip this user if their preference disables in_app for this trigger
            if (! self::isEnabledForUser($user->id, $triggerKey, 'in_app')) {
                self::log($triggerKey, 'web', $module, $user->email ?? $user->name, 'skipped', 'User preference: disabled');
                continue;
            }

            try {
                $user->notify(new \App\Notifications\InAppNotification(
                    $triggerKey,
                    $title,
                    $message,
                    $url,
                    $module,
                    $isSla,
                ));

                self::log($triggerKey, 'web', $module, $user->email ?? $user->name, 'sent');
            } catch (\Throwable $e) {
                self::log($triggerKey, 'web', $module, $user->email ?? $user->name, 'failed', $e->getMessage());
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    //  Logging helper
    // ──────────────────────────────────────────────────────────
    protected static function log(
        string  $triggerKey,
        string  $channel,
        ?string $module,
        string  $sentTo,
        string  $status,
        ?string $error = null,
    ): void {
        try {
            NotificationLog::create([
                'trigger_key' => $triggerKey,
                'channel'     => $channel,
                'module'      => $module,
                'sent_to'     => $sentTo,
                'status'      => $status,
                'error'       => $error,
                'payload'     => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('NotificationService::log failed: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────
    //  Clear channels cache (call after updating settings)
    // ──────────────────────────────────────────────────────────
    public static function clearCache(): void
    {
        Cache::forget('notif_channels');

        // Note: Per-user trigger preference caches (user_{id}_notif_prefs)
        // are short-lived (60s TTL) and self-invalidate on preference changes.
        // No need to flush all user caches here — the resolved state recalculates
        // using the fresh channel state on the next request.
    }
}
