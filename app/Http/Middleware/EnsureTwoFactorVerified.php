<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Not logged in? let auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // Superadmin bypass 2FA (as you want)
        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return $next($request);
        }

        // If user has 2FA enabled but not verified in this session
        if ($user->two_factor_enabled && !$request->session()->get('2fa_passed')) {
            // Avoid redirect loop
            if (!$request->routeIs('2fa.verify.form', '2fa.verify', '2fa.setup', '2fa.enable')) {
                return redirect()->route('2fa.verify.form');
            }
        }

        return $next($request);
    }
}
