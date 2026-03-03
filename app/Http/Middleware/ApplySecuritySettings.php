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
        // Keep pre-auth/bootstrap routes fast and DB-independent.
        // If DB is slow/unavailable, these routes must still respond immediately.
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        try {
            $settings = SecuritySetting::current();

            // Session lifetime controls how long the server keeps the session alive
            // and when the session cookie expires.
            //
            // IMPORTANT: This must NOT equal the inactivity timeout.
            // The inactivity timeout (auto_logout_after_minutes) is enforced by
            // the frontend (useInactivityLogout) which tracks mouse/keyboard/scroll
            // activity and triggers a logout API call when idle too long.
            //
            // We set session.lifetime to 24 hours so the server-side session
            // and cookie stay valid while the user has the app open.
            // If force_logout_on_close is enabled, the cookie expires when the
            // browser closes regardless of this lifetime.
            config([
                'session.lifetime'        => 1440, // 24 hours
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

    private function shouldBypass(Request $request): bool
    {
        if ($request->is('sanctum/csrf-cookie')) {
            return true;
        }

        if ($request->is('favicon.ico') || $request->is('up')) {
            return true;
        }

        if ($request->is('login') || $request->is('register') || $request->is('forgot-password') || $request->is('reset-password/*')) {
            return true;
        }

        return false;
    }
}
