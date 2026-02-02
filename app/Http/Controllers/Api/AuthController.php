<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login – supports both session and token auth.
     * - Session (default): Sets cookie, returns { redirect }.
     * - Token: Header X-Request-Token: true → returns { token } for Bearer auth.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $user = Auth::user();

        if ($user->status !== 'approved') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Your registration is completed. Please wait for super admin approval.'],
            ]);
        }

        $wantsToken = $request->boolean('token') || $request->header('X-Request-Token') === 'true';

        if ($wantsToken) {
            $token = $user->createToken('api')->plainTextToken;
            Auth::logout();
            $roles = $user->roles->pluck('name')->toArray();
            return response()->json([
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => array_merge($user->only(['id', 'name', 'email']), ['roles' => $roles]),
            ]);
        }

        $request->session()->regenerate();

        UserLoginLog::where('user_id', auth()->id())
            ->whereNull('logout_at')
            ->latest('login_at')
            ->limit(1)
            ->update(['session_id' => $request->session()->getId()]);

        if ($user->hasRole('superadmin')) {
            $request->session()->put('2fa_passed', true);
            return response()->json(['redirect' => '/']);
        }

        if ($user->two_factor_enabled) {
            $request->session()->forget('2fa_passed');
            return response()->json(['redirect' => '/2fa/verify']);
        }

        $request->session()->put('2fa_passed', true);
        return response()->json(['redirect' => '/']);
    }

    /**
     * Logout – works with session or token (revokes current token).
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->bearerToken()) {
            $request->user()->currentAccessToken()->delete();
        } else {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Verify 2FA OTP – API version (session-based SPA).
     */
    public function verify2FA(Request $request): JsonResponse
    {
        $request->validate(['otp' => ['required', 'string']]);

        $user = $request->user();
        if (!$user?->two_factor_enabled) {
            $request->session()->put('2fa_passed', true);
            return response()->json(['redirect' => '/']);
        }

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->input('otp'));

        if (!$valid) {
            throw ValidationException::withMessages(['otp' => ['Invalid OTP code.']]);
        }

        $request->session()->put('2fa_passed', true);
        return response()->json(['redirect' => '/']);
    }
}
