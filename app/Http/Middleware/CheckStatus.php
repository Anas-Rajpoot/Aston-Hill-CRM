<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckStatus
{
    /**
     * Block deactivated (and non-approved) users from performing any action.
     * Login is already blocked in AuthController / AuthenticatedSessionController.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || auth()->user()->status === 'approved') {
            return $next($request);
        }

        $user = auth()->user();
        // Revoke current API token so deactivated user cannot keep using it
        if ($request->bearerToken() && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }
        Auth::logout();

        if ($request->expectsJson() || $request->bearerToken()) {
            return response()->json([
                'message' => 'Your account has been deactivated. You cannot perform any action.',
            ], 401);
        }

        abort(403);
    }
}
