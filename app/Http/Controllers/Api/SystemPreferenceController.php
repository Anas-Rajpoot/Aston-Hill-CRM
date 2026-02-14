<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemPreferencesRequest;
use App\Models\SystemAuditLog;
use App\Models\SystemPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemPreferenceController extends Controller
{
    /** Fields stored in the preferences row. */
    private const PREF_FIELDS = [
        'timezone',
        'default_dashboard_landing_page',
        'default_table_page_size',
        'auto_refresh_dashboard',
        'auto_refresh_interval_minutes',
        'auto_save_draft_forms',
        'session_warning_before_logout',
        'session_warning_minutes',
    ];

    /**
     * GET /api/system-preferences – returns current prefs + can_update flag.
     */
    public function index(Request $request): JsonResponse
    {
        $prefs = SystemPreference::singleton();
        $user = $request->user();
        $canUpdate = $user && ($user->hasRole('superadmin') || $user->can('manage-system-preferences'));

        $data = collect(self::PREF_FIELDS)->mapWithKeys(fn ($f) => [$f => $prefs->{$f}])->toArray();

        return response()->json([
            'data' => $data,
            'meta' => [
                'can_update' => $canUpdate,
                'updated_at' => $prefs->updated_at?->toIso8601String(),
                'updated_by_name' => $prefs->updater?->name,
                'defaults' => SystemPreference::DEFAULTS,
            ],
        ]);
    }

    /**
     * PUT /api/system-preferences – update preferences (super admin only).
     */
    public function update(UpdateSystemPreferencesRequest $request): JsonResponse
    {
        $prefs = SystemPreference::singleton();
        $oldValues = collect(self::PREF_FIELDS)->mapWithKeys(fn ($f) => [$f => $prefs->getOriginal($f) ?? $prefs->{$f}])->toArray();

        $prefs->fill($request->only(self::PREF_FIELDS));
        $prefs->updated_by = $request->user()->id;
        $prefs->save();

        SystemPreference::clearCache();
        // Invalidate bootstrap cache so all users pick up new timezone
        \App\Http\Controllers\Api\BootstrapController::invalidate();

        $newValues = collect(self::PREF_FIELDS)->mapWithKeys(fn ($f) => [$f => $prefs->{$f}])->toArray();

        // Only log if something actually changed
        $changed = array_filter($oldValues, fn ($v, $k) => $v !== ($newValues[$k] ?? null), ARRAY_FILTER_USE_BOTH);
        if (! empty($changed)) {
            SystemAuditLog::record(
                'system_preferences.updated',
                $changed,
                array_intersect_key($newValues, $changed),
                $request->user()->id,
            );
        }

        return response()->json([
            'message' => 'System preferences updated.',
            'data' => $newValues,
        ]);
    }

    /**
     * POST /api/system-preferences/reset – restore defaults (super admin only).
     */
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('superadmin') && ! $user->can('manage-system-preferences'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $prefs = SystemPreference::singleton();
        $oldValues = collect(self::PREF_FIELDS)->mapWithKeys(fn ($f) => [$f => $prefs->{$f}])->toArray();

        $prefs->fill(SystemPreference::DEFAULTS);
        $prefs->updated_by = $user->id;
        $prefs->save();

        SystemPreference::clearCache();
        \App\Http\Controllers\Api\BootstrapController::invalidate();

        SystemAuditLog::record(
            'system_preferences.reset',
            $oldValues,
            SystemPreference::DEFAULTS,
            $user->id,
        );

        return response()->json([
            'message' => 'System preferences reset to defaults.',
            'data' => SystemPreference::DEFAULTS,
        ]);
    }
}
