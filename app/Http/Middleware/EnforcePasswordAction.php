<?php

namespace App\Http\Middleware;

use App\Models\SecuritySetting;
use Closure;
use Illuminate\Http\Request;

/**
 * Enforce password actions configured in security settings.
 * Currently this only handles first-login password change.
 */
class EnforcePasswordAction
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
