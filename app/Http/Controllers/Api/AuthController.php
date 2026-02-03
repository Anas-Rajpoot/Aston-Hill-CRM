<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserLoginLog;
use App\Services\UserPermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login – supports both session and token auth.
     * Session login returns user + roles + permissions so the frontend can skip an immediate /bootstrap call.
     * Uses UserPermissionResolver (2 queries) instead of hasRole() to avoid Spatie N+1.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
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
            $resolved = UserPermissionResolver::getRolesAndPermissions((int) $user->id, $user->getMorphClass());
            return response()->json([
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $resolved['roles'],
                    'permissions' => $resolved['permissions'],
                ],
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget(\App\Http\Middleware\EnsureTwoFactorVerified::SESSION_ROLES_KEY);

        // Update session_id on latest open log (for logout tracking). Single query.
        UserLoginLog::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->limit(1)
            ->update(['session_id' => $request->session()->getId()]);

        // One resolution for both superadmin check and response payload (no hasRole()).
        $resolved = UserPermissionResolver::getRolesAndPermissions((int) $user->id, $user->getMorphClass());
        $roles = $resolved['roles'];
        $permissions = $resolved['permissions'];

        if (in_array('superadmin', $roles, true)) {
            $request->session()->put('2fa_passed', true);
            return response()->json([
                'redirect' => '/',
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $roles],
                'permissions' => $permissions,
            ]);
        }

        if ($user->two_factor_enabled) {
            $request->session()->forget('2fa_passed');
            return response()->json(['redirect' => '/2fa/verify']);
        }

        $request->session()->put('2fa_passed', true);
        return response()->json([
            'redirect' => '/',
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $roles],
            'permissions' => $permissions,
        ]);
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
