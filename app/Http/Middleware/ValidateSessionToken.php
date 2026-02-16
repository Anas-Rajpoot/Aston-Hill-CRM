<?php

namespace App\Http\Middleware;

use App\Models\SecuritySetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Enforces single-session policy when "Prevent Multiple Sessions" is ENABLED.
 *
 * When prevent_multiple_sessions = true, compares the session's _session_token
 * with the user's active_session_token in the DB. If they don't match, another
 * device logged in and this session must be terminated.
 *
 * When prevent_multiple_sessions = false, multiple sessions are allowed and
 * this middleware is a no-op.
 */
class ValidateSessionToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Skip validation for token-based auth (Sanctum tokens are validated inherently;
        // old tokens were already revoked at login time).
        if ($request->bearerToken()) {
            return $next($request);
        }

        try {
            $security = SecuritySetting::current();

            // Only enforce single-session when the toggle is ON.
            // When prevent_multiple_sessions = false, allow multiple sessions freely.
            if (! $security->prevent_multiple_sessions) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            return $next($request);
        }

        // Compare session token with DB
        $sessionToken = $request->session()->get('_session_token');
        $dbToken = $user->active_session_token;

        // If user has no DB token yet (legacy session before feature deployment), skip.
        // Enforcement starts after their next login sets both tokens.
        if (! $dbToken) {
            return $next($request);
        }

        // If session has no token but DB does, another login rotated the DB token.
        // If tokens exist but don't match, another device logged in.
        if (! $sessionToken || $sessionToken !== $dbToken) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session was ended because you logged in on another device.',
                    'reason'  => 'session_terminated',
                ], 401);
            }

            return redirect('/login');
        }

        return $next($request);
    }
}
