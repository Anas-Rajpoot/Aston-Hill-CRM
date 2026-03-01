<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecuritySetting;
use App\Models\SystemAuditLog;
use App\Rules\MeetsPasswordPolicy;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Handles password changes for authenticated users.
 * Enforces the dynamic password policy from security_settings.
 */
class ChangePasswordController extends Controller
{
    /**
     * GET /api/change-password/policy
     * Returns the current password policy so the frontend can show requirements.
     */
    public function policy(Request $request): JsonResponse
    {
        $settings = SecuritySetting::current();
        $user = $request->user();

        // Super admins are exempt from password expiry – they change when they choose to.
        $isSuperAdmin = $user?->hasRole('superadmin') ?? false;

        $reason = null;
        if ($user) {
            if ($user->must_change_password) {
                $reason = 'must_change_password';
            } elseif (! $isSuperAdmin && $settings->password_expiry_days > 0) {
                if (! $user->password_changed_at) {
                    $reason = 'password_expired';
                } elseif ($user->password_changed_at->addDays($settings->password_expiry_days)->isPast()) {
                    $reason = 'password_expired';
                }
            }
        }

        return response()->json([
            'data' => [
                'min_length'        => $settings->min_length,
                'require_uppercase' => (bool) $settings->require_uppercase,
                'require_number'    => (bool) $settings->require_number,
                'require_special'   => (bool) $settings->require_special,
                'expiry_days'       => $settings->password_expiry_days,
                'reason'            => $reason,
            ],
        ]);
    }

    /**
     * POST /api/change-password
     * Validate current password, enforce policy on new password, update user.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'confirmed', new MeetsPasswordPolicy],
        ], [
            'password.confirmed' => 'The new password confirmation does not match.',
        ]);

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Prevent reusing the same password
        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The new password must be different from your current password.'],
            ]);
        }

        // Update password and clear flags
        $user->update([
            'password'             => Hash::make($request->password),
            'password_changed_at'  => now(),
            'must_change_password' => false,
        ]);

        // Audit log (system_audit_logs)
        SystemAuditLog::record(
            'user.password_changed',
            ['user_id' => $user->id],
            ['password_changed_at' => now()->toIso8601String()],
            $user->id,
            'user',
            $user->id,
        );

        // Audit log (audit_logs — visible on Audit Logs page)
        AuditLogger::record([
            'action'     => 'password_reset',
            'module'     => 'Password Change',
            'record_id'  => $user->id,
            'record_ref' => $user->email,
            'result'     => 'success',
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
