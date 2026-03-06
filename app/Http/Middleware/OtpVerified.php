<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OtpVerified
{
    /**
     * Handle an incoming request.
     *
     * For API (expects JSON): return 403 with structured error.
     * For web (session): redirect to OTP verify form.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('otp_verified')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'OTP verification required.',
                    'otp_required' => true,
                ], 403);
            }

            return redirect()->route('otp.verify');
        }

        return $next($request);
    }
}
