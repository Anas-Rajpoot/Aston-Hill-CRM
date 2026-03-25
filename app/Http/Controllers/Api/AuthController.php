<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecuritySetting;
use App\Models\SystemAuditLog;
use App\Models\SystemPreference;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\AuditLogger;
use App\Services\UserPermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** @var array<string,bool> */
    private static array $userColumnExistsCache = [];

    /**
     * Login – supports both session and token auth.
     * Enforces: account locking, status check, password change redirect.
     * Uses UserPermissionResolver (2 queries) instead of hasRole() to avoid Spatie N+1.
     */
    public function login(Request $request): JsonResponse
    {
        // If an authenticated session/token already exists, return landing redirect
        // instead of re-showing login flow.
        $alreadyUser = $request->user();
        if ($alreadyUser) {
            $resolved = $this->safeResolveRolesAndPermissions($alreadyUser);
            $settings = $this->resolveSecuritySettings();
            $passwordAction = null;
            if (($settings->force_password_reset_on_first_login ?? false) && $alreadyUser->must_change_password) {
                $passwordAction = 'must_change_password';
            }

            return response()->json([
                'redirect' => $passwordAction ? '/change-password' : $this->resolveHomeRedirect(),
                'user' => [
                    'id' => $alreadyUser->id,
                    'name' => $alreadyUser->name,
                    'email' => $alreadyUser->email,
                    'roles' => $resolved['roles'],
                ],
                'permissions' => $resolved['permissions'],
                'password_action' => $passwordAction,
            ]);
        }

        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ── Load security settings once for the entire login flow ──
        $security = $this->resolveSecuritySettings();

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
            $this->safeFillAndSaveUser($targetUser, [
                'locked_until'         => null,
                'failed_login_attempts' => 0,
            ]);
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

                $this->safeFillAndSaveUser($targetUser, $update);

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
            $this->safeFillAndSaveUser($user, [
                'failed_login_attempts' => 0,
                'locked_until'          => null,
            ]);
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
            // Revoke all existing Sanctum tokens for this user.
            // Guard against partially-migrated environments where Sanctum tables may be missing.
            try {
                $terminatedCount = $user->tokens()->count();
                $user->tokens()->delete();
            } catch (\Throwable $e) {
                Log::warning('Auth login: failed to clear previous Sanctum tokens.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $terminatedCount = 0;
            }
        }

        // Always rotate the session token so the middleware can validate
        $this->safeFillAndSaveUser($user, ['active_session_token' => $sessionToken]);

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
        $passwordAction = $this->resolvePasswordAction($user, (string) $request->input('password'));

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
            $resolved = $this->safeResolveRolesAndPermissions($user);
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
        $resolved = $this->safeResolveRolesAndPermissions($user);
        $roles = $resolved['roles'];
        $permissions = $resolved['permissions'];

        // If password change required, redirect to change-password; otherwise use configured landing page.
        $redirect = $passwordAction ? '/change-password' : $this->resolveHomeRedirect();

        if (in_array('superadmin', $roles, true)) {
            $request->session()->put('2fa_passed', true);
            return response()->json([
                'redirect' => $redirect,
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $roles],
                'permissions' => $permissions,
                'password_action' => $passwordAction,
            ]);
        }

        if ((bool) config('auth.disable_google_authentication', false)) {
            $request->session()->put('2fa_passed', true);
        } elseif ($user->two_factor_enabled) {
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
     * Returns null if no action needed, or 'must_change_password'.
     */
    private function resolvePasswordAction(User $user, string $plainPassword): ?string
    {
        try {
            $settings = $this->resolveSecuritySettings();

            // First-login password reset
            if ($settings->force_password_reset_on_first_login && $user->must_change_password) {
                if ($this->isPlainPasswordPolicyCompliant($plainPassword, $settings)) {
                    $this->safeFillAndSaveUser($user, [
                        'must_change_password' => false,
                        'password_changed_at' => $user->password_changed_at ?: now(),
                    ]);
                    return null;
                }
                return 'must_change_password';
            }

        } catch (\Throwable $e) {
            // Silently fail during migrations
        }

        return null;
    }

    /**
     * Resolve configured home route from System Preferences.
     */
    private function resolveHomeRedirect(): string
    {
        try {
            $landing = (string) (SystemPreference::singleton()->default_dashboard_landing_page ?? 'dashboard');
            $landing = trim($landing);
            if ($landing === '' || $landing === 'dashboard') {
                return '/';
            }
            return '/'.ltrim($landing, '/');
        } catch (\Throwable $e) {
            return '/';
        }
    }

    /**
     * Validate plaintext password against current security policy.
     *
     * @param object $settings Security settings object or DEFAULTS fallback object.
     */
    private function isPlainPasswordPolicyCompliant(string $password, object $settings): bool
    {
        $minLength = (int) ($settings->min_length ?? 8);
        if (mb_strlen($password) < $minLength) {
            return false;
        }
        if ((bool) ($settings->require_uppercase ?? false) && ! preg_match('/[A-Z]/', $password)) {
            return false;
        }
        if ((bool) ($settings->require_number ?? false) && ! preg_match('/[0-9]/', $password)) {
            return false;
        }
        if ((bool) ($settings->require_special ?? false) && ! preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }
        return true;
    }

    /**
     * Resolve SecuritySetting singleton, but never let DB/config errors crash auth.
     */
    private function resolveSecuritySettings()
    {
        try {
            return SecuritySetting::current();
        } catch (\Throwable $e) {
            Log::warning('Auth login: failed to load security settings, using defaults.', [
                'error' => $e->getMessage(),
            ]);
            return (object) SecuritySetting::DEFAULTS;
        }
    }

    /**
     * Resolve roles/permissions without allowing resolver errors to 500 login.
     *
     * @return array{roles: array<int,string>, permissions: array<int,string>}
     */
    private function safeResolveRolesAndPermissions(User $user): array
    {
        try {
            return UserPermissionResolver::getRolesAndPermissions((int) $user->id, $user->getMorphClass());
        } catch (\Throwable $e) {
            Log::warning('Auth login: failed to resolve roles/permissions.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return ['roles' => [], 'permissions' => []];
        }
    }

    private function safeFillAndSaveUser(User $user, array $attributes): void
    {
        $filtered = [];
        foreach ($attributes as $column => $value) {
            if ($this->hasUserColumn((string) $column)) {
                $filtered[$column] = $value;
            }
        }
        if ($filtered === []) {
            return;
        }
        $user->forceFill($filtered)->saveQuietly();
    }

    private function hasUserColumn(string $column): bool
    {
        if (array_key_exists($column, self::$userColumnExistsCache)) {
            return self::$userColumnExistsCache[$column];
        }
        try {
            self::$userColumnExistsCache[$column] = Schema::hasColumn('users', $column);
        } catch (\Throwable $e) {
            self::$userColumnExistsCache[$column] = false;
            Log::warning('Auth login: failed to inspect users column.', [
                'column' => $column,
                'error' => $e->getMessage(),
            ]);
        }
        return self::$userColumnExistsCache[$column];
    }

    /**
     * Logout – works with session or token (revokes current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        AuditLogger::record([
            'action'     => 'logout',
            'module'     => 'Authentication',
            'record_id'  => $user?->id,
            'record_ref' => $user?->email,
            'result'     => 'success',
        ]);

        // Ensure attendance row is closed even when event listeners are skipped/misconfigured.
        if ($user) {
            $logoutQuery = UserLoginLog::where('user_id', $user->id)->whereNull('logout_at');
            if (! empty($sessionId)) {
                $logoutQuery->where(function ($q) use ($sessionId) {
                    $q->where('session_id', $sessionId)->orWhereNull('session_id');
                });
            }
            $logoutQuery->latest('login_at')->limit(1)->update(['logout_at' => now()]);
        }

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
        if ((bool) config('auth.disable_google_authentication', false)) {
            $request->session()->put('2fa_passed', true);
            return response()->json(['redirect' => $this->resolveHomeRedirect()]);
        }

        $request->validate(['otp' => ['required', 'string']]);

        $user = $request->user();
        if (!$user?->two_factor_enabled) {
            $request->session()->put('2fa_passed', true);
            return response()->json(['redirect' => $this->resolveHomeRedirect()]);
        }

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->input('otp'));

        if (!$valid) {
            throw ValidationException::withMessages(['otp' => ['Invalid OTP code.']]);
        }

        $request->session()->put('2fa_passed', true);
        return response()->json(['redirect' => $this->resolveHomeRedirect()]);
    }
}
