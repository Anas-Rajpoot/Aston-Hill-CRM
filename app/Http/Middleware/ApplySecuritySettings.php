<?php

namespace App\Http\Middleware;

use App\Models\NotificationSetting;
use App\Models\SecuritySetting;
use App\Models\SystemPreference;
use Closure;
use Illuminate\Http\Request;

/**
 * Applies session and system settings at runtime from singletons.
 * Should run early in the middleware stack on every request.
 */
class ApplySecuritySettings
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $settings = SecuritySetting::current();

            config([
                'session.lifetime'        => $settings->auto_logout_after_minutes,
                'session.expire_on_close' => $settings->force_logout_on_close,
            ]);
        } catch (\Throwable $e) {
            // Silently fail if table doesn't exist yet (e.g. during migration)
        }

        // Apply system timezone globally so all Carbon/date functions use it
        try {
            $prefs = SystemPreference::singleton();
            $tz = $prefs->timezone ?? 'Asia/Dubai';
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        } catch (\Throwable $e) {
            // Silently fail if table doesn't exist yet
        }

        // Apply global email from/cc/bcc from Notification Settings
        try {
            $emailCfg = NotificationSetting::emailConfig();
            if ($emailCfg['from']) {
                config(['mail.from.address' => $emailCfg['from']]);
            }
        } catch (\Throwable $e) {
            // Silently fail if table doesn't exist yet
        }

        return $next($request);
    }
}
