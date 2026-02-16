<?php

namespace App\Http\Middleware;

use App\Models\SecuritySetting;
use Closure;
use Illuminate\Http\Request;

/**
 * If password_expiry_days > 0 and user's password_changed_at is older than threshold,
 * flag the response so the frontend can redirect to change-password flow.
 */
class EnforcePasswordExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        try {
            $settings = SecuritySetting::current();

            // Check must_change_password flag (first-login enforcement)
            if ($settings->force_password_reset_on_first_login && $user->must_change_password) {
                return $this->addPasswordFlag($request, $next, 'must_change_password');
            }

            // Super admins are exempt from password expiry – they change when they choose to.
            if ($user->hasRole('superadmin')) {
                return $next($request);
            }

            // Check password expiry
            if ($settings->password_expiry_days > 0 && $user->password_changed_at) {
                $expiresAt = $user->password_changed_at->addDays($settings->password_expiry_days);
                if ($expiresAt->isPast()) {
                    return $this->addPasswordFlag($request, $next, 'password_expired');
                }
            }

            // If password_changed_at is null and expiry is enabled, treat as expired
            if ($settings->password_expiry_days > 0 && ! $user->password_changed_at) {
                return $this->addPasswordFlag($request, $next, 'password_expired');
            }
        } catch (\Throwable $e) {
            // Silently fail during migrations
        }

        return $next($request);
    }

    private function addPasswordFlag(Request $request, Closure $next, string $reason)
    {
        $response = $next($request);

        // Add header flag so frontend can react (redirect to change-password)
        $response->headers->set('X-Password-Action', $reason);

        return $response;
    }
}
