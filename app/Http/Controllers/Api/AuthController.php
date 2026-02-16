<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecuritySetting;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\AuditLogger;
use App\Services\UserPermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login – supports both session and token auth.
     * Enforces: account locking, status check, password change redirect.
     * Uses UserPermissionResolver (2 queries) instead of hasRole() to avoid Spatie N+1.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ── Load security settings once for the entire login flow ──
        $security = SecuritySetting::current();

        // ── Check account lock BEFORE attempting auth ──
        $targetUser = User::where('email', $request->email)->first();
        if ($targetUser && $targetUser->locked_until && $targetUser->locked_until->isFuture()) {
            $minutesLeft = (int) now()->diffInMinutes($targetUser->locked_until, false);
            throw ValidationException::withMessages([
                'email' => ["Your account is temporarily locked. Please try again in {$minutesLeft} minute(s)."],
            ]);
        }

        // Clear an expired lock so the user can attempt again
        if ($targetUser && $targetUser->locked_until && $targetUser->locked_until->isPast()) {
            $targetUser->forceFill([
                'locked_until'         => null,
                'failed_login_attempts' => 0,
            ])->saveQuietly();
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            // ── Brute-force protection: track failed attempts ──
            if ($targetUser) {
                $attempts = ($targetUser->failed_login_attempts ?? 0) + 1;
                $update   = ['failed_login_attempts' => $attempts];

                if ($security->lock_after_failed_attempts && $attempts >= $security->max_login_attempts) {
                    $lockUntil = now()->addMinutes($security->lock_duration_minutes);
                    $update['locked_until'] = $lockUntil;

                    SystemAuditLog::record(
                        'auth.account_locked',
                        ['failed_attempts' => $attempts, 'max_allowed' => $security->max_login_attempts],
                        ['locked_until' => $lockUntil->toIso8601String(), 'lock_duration_minutes' => $security->lock_duration_minutes],
                        $targetUser->id,
                        'user',
                        $targetUser->id,
                    );
                }

                $targetUser->forceFill($update)->saveQuietly();

                // If we just locked, return the lock message instead of generic error
                if (isset($lockUntil)) {
                    throw ValidationException::withMessages([
                        'email' => ["Too many failed attempts. Your account has been locked for {$security->lock_duration_minutes} minute(s)."],
                    ]);
                }
            }

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $user = Auth::user();

        // ── Successful login: reset failed attempts and clear any lock ──
        if ($user->failed_login_attempts > 0 || $user->locked_until) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until'          => null,
            ])->saveQuietly();
        }

        if ($user->status !== 'approved') {
            Auth::logout();
            $message = $user->status === 'rejected'
                ? 'Your account has been deactivated. Contact the administrator.'
                : 'Your registration is completed. Please wait for super admin approval.';
            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        $wantsToken = $request->boolean('token') || $request->header('X-Request-Token') === 'true';

        // ── Enforce single-session when "Prevent Multiple Sessions" is DISABLED ──
        $sessionToken = Str::random(64);
        $terminatedCount = 0;

        if (! $security->prevent_multiple_sessions) {
            // Revoke all existing Sanctum tokens for this user
            $terminatedCount = $user->tokens()->count();
            $user->tokens()->delete();
        }

        // Always rotate the session token so the middleware can validate
        $user->forceFill(['active_session_token' => $sessionToken])->saveQuietly();

        if ($terminatedCount > 0) {
            SystemAuditLog::record(
                'session.other_sessions_terminated',
                ['terminated_tokens' => $terminatedCount],
                ['reason' => 'single_session_enforced', 'new_login_ip' => $request->ip()],
                $user->id,
                'user',
                $user->id,
            );
        }

        // ── Determine if password change is required ──
        $passwordAction = $this->resolvePasswordAction($user);

        // ── Audit log for successful login ──
        AuditLogger::record([
            'action'     => 'login',
            'module'     => 'Authentication',
            'record_id'  => $user->id,
            'record_ref' => $user->email,
            'result'     => 'success',
        ]);

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
                'password_action' => $passwordAction,
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget(\App\Http\Middleware\EnsureTwoFactorVerified::SESSION_ROLES_KEY);

        // Store session token in the session for middleware validation
        $request->session()->put('_session_token', $sessionToken);

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

        // If password change required, redirect to change-password instead of home
        $redirect = $passwordAction ? '/change-password' : '/';

        if (in_array('superadmin', $roles, true)) {
            $request->session()->put('2fa_passed', true);
            return response()->json([
                'redirect' => $redirect,
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $roles],
                'permissions' => $permissions,
                'password_action' => $passwordAction,
            ]);
        }

        if ($user->two_factor_enabled) {
            $request->session()->forget('2fa_passed');
            return response()->json(['redirect' => '/2fa/verify']);
        }

        $request->session()->put('2fa_passed', true);
        return response()->json([
            'redirect' => $redirect,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $roles],
            'permissions' => $permissions,
            'password_action' => $passwordAction,
        ]);
    }

    /**
     * Check if user must change their password.
     * Returns null if no action needed, or 'must_change_password' / 'password_expired'.
     */
    private function resolvePasswordAction(User $user): ?string
    {
        try {
            $settings = SecuritySetting::current();

            // First-login password reset
            if ($settings->force_password_reset_on_first_login && $user->must_change_password) {
                return 'must_change_password';
            }

            // Super admins are exempt from password expiry – they change when they choose to.
            if ($user->hasRole('superadmin')) {
                return null;
            }

            // Password expiry
            if ($settings->password_expiry_days > 0) {
                if (! $user->password_changed_at) {
                    return 'password_expired';
                }
                $expiresAt = $user->password_changed_at->addDays($settings->password_expiry_days);
                if ($expiresAt->isPast()) {
                    return 'password_expired';
                }
            }
        } catch (\Throwable $e) {
            // Silently fail during migrations
        }

        return null;
    }

    /**
     * Logout – works with session or token (revokes current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        AuditLogger::record([
            'action'     => 'logout',
            'module'     => 'Authentication',
            'record_id'  => $user?->id,
            'record_ref' => $user?->email,
            'result'     => 'success',
        ]);

        if ($request->bearerToken()) {
            $user->currentAccessToken()->delete();
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
