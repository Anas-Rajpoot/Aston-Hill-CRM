<?php

namespace App\Http\Middleware;

use App\Services\UserPermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public const SESSION_ROLES_KEY = '_user_roles';

    /**
     * Handle an incoming request.
     * Superadmin bypass is cached in session so we do not run Spatie role queries on every request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('auth.disable_google_authentication', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Superadmin bypass 2FA: use session-cached roles so we avoid hasRole() DB hit every request.
        $roles = $request->session()->get(self::SESSION_ROLES_KEY);
        if ($roles === null) {
            $resolved = UserPermissionResolver::getRolesAndPermissions((int) $user->id, $user->getMorphClass());
            $roles = $resolved['roles'];
            $request->session()->put(self::SESSION_ROLES_KEY, $roles);
        }
        if (in_array('superadmin', $roles, true)) {
            return $next($request);
        }

        // If user has 2FA enabled but not verified in this session
        if ($user->two_factor_enabled && !$request->session()->get('2fa_passed')) {
            if ($request->routeIs('2fa.verify.form', '2fa.verify', '2fa.setup', '2fa.enable', 'api.auth.2fa.verify')) {
                return $next($request);
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => '2FA verification required', 'redirect' => '/2fa/verify'], 403);
            }
            return redirect()->route('2fa.verify.form');
        }

        return $next($request);
    }
}
