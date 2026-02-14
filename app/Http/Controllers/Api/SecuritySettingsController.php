<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSecuritySettingsRequest;
use App\Http\Resources\SecuritySettingResource;
use App\Models\SecuritySetting;
use App\Models\SystemAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecuritySettingsController extends Controller
{
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

        $data['updated_by'] = $request->user()->id;
        $settings->update($data);
        SecuritySetting::clearCache();

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

        $settings->update(array_merge(SecuritySetting::DEFAULTS, [
            'updated_by' => $request->user()->id,
        ]));
        SecuritySetting::clearCache();

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
}
