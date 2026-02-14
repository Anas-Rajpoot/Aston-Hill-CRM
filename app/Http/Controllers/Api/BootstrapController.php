<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecuritySetting;
use App\Models\SystemPreference;
use App\Services\UserPermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight bootstrap for progressive rendering: auth user (id, name, email) + roles + permission names.
 * Uses fixed 2-query permission resolution (no Spatie getAllPermissions). Cached per user (TTL 5 min).
 */
class BootstrapController extends Controller
{
    private const TTL_SECONDS = 300;
    private const VERSION_KEY = 'bootstrap_version';

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $version = Cache::get(self::VERSION_KEY, 0);
        $cacheKey = 'bootstrap_'.$version.'_'.$user->id;

        $data = Cache::remember($cacheKey, self::TTL_SECONDS, function () use ($user) {
            // Resolve roles + permissions in 2 DB queries (no getAllPermissions, no N+1).
            $resolved = UserPermissionResolver::getRolesAndPermissions(
                (int) $user->id,
                $user->getMorphClass()
            );

            $prefs    = SystemPreference::singleton();
            $security = SecuritySetting::current();

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $resolved['roles'],
                ],
                'permissions' => $resolved['permissions'],
                'timezone' => $prefs->timezone ?? 'Asia/Dubai',
                'session' => [
                    'timeout_minutes'         => (int) $security->auto_logout_after_minutes,
                    'warning_enabled'         => (bool) $prefs->session_warning_before_logout,
                    'warning_minutes_before'  => (int) ($prefs->session_warning_minutes ?? $security->session_warning_minutes ?? 5),
                ],
            ];
        });

        return response()
            ->json($data)
            ->header('Cache-Control', 'private, max-age=300');
    }

    /** Invalidate all bootstrap caches when roles/permissions change. */
    public static function invalidate(): void
    {
        Cache::put(self::VERSION_KEY, time(), 86400);
    }
}
