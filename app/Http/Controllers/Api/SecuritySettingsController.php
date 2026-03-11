<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSecuritySettingsRequest;
use App\Http\Resources\SecuritySettingResource;
use App\Models\SecuritySetting;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecuritySettingsController extends Controller
{
    /** @var array<int,string> */
    private const PASSWORD_POLICY_FIELDS = [
        'min_length',
        'require_uppercase',
        'require_number',
        'require_special',
    ];

    private function canManage($user): bool
    {
        return $user && ($user->hasRole('superadmin') || $user->can('manage-security-settings'));
    }

    /* ── GET /api/security-settings ── */
    public function index(Request $request): JsonResponse
    {
        $settings = SecuritySetting::current();
        $settings->load('updater');

        return response()->json([
            'data' => new SecuritySettingResource($settings),
            'meta' => [
                'can_update' => $this->canManage($request->user()),
            ],
        ]);
    }

    /* ── PUT /api/security-settings ── */
    public function update(UpdateSecuritySettingsRequest $request): JsonResponse
    {
        $settings = SecuritySetting::current();
        $data     = $request->validated();
        $old      = $settings->only(array_keys($data));
        $policyChanged = $this->hasPasswordPolicyChanged($old, $data);

        $data['updated_by'] = $request->user()->id;
        $settings->update($data);
        SecuritySetting::clearCache();

        if ($policyChanged) {
            $this->flagAllUsersForPasswordChange();
        }

        // Audit: log only changed fields
        $changed = array_filter($old, fn ($v, $k) => json_encode($v) !== json_encode($data[$k] ?? null), ARRAY_FILTER_USE_BOTH);
        if (! empty($changed)) {
            SystemAuditLog::record(
                'security_settings.updated',
                $changed,
                array_intersect_key($data, $changed),
                $request->user()->id,
                'security_setting',
                $settings->id,
            );
        }

        return response()->json([
            'message' => 'Security settings updated.',
            'data'    => new SecuritySettingResource($settings->fresh()->load('updater')),
        ]);
    }

    /* ── POST /api/security-settings/reset ── */
    public function reset(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $settings = SecuritySetting::current();
        $old      = $settings->only(array_keys(SecuritySetting::DEFAULTS));
        $policyChanged = $this->hasPasswordPolicyChanged($old, SecuritySetting::DEFAULTS);

        $settings->update(array_merge(SecuritySetting::DEFAULTS, [
            'updated_by' => $request->user()->id,
        ]));
        SecuritySetting::clearCache();

        if ($policyChanged) {
            $this->flagAllUsersForPasswordChange();
        }

        SystemAuditLog::record(
            'security_settings.reset',
            $old,
            SecuritySetting::DEFAULTS,
            $request->user()->id,
            'security_setting',
            $settings->id,
        );

        return response()->json([
            'message' => 'Security settings reset to defaults.',
            'data'    => new SecuritySettingResource($settings->fresh()->load('updater')),
        ]);
    }

    private function hasPasswordPolicyChanged(array $old, array $next): bool
    {
        foreach (self::PASSWORD_POLICY_FIELDS as $field) {
            if (! array_key_exists($field, $next)) {
                continue;
            }

            $oldValue = $old[$field] ?? null;
            $newValue = $next[$field] ?? null;

            if (json_encode($oldValue) !== json_encode($newValue)) {
                return true;
            }
        }

        return false;
    }

    private function flagAllUsersForPasswordChange(): void
    {
        User::query()->update([
            'must_change_password' => true,
        ]);
    }
}
