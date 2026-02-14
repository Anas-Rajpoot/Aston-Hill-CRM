<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecuritySetting;
use App\Models\SystemPreference;
use App\Services\UserPermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MeController extends Controller
{
    private const TTL = 60;

    /**
     * Return current user with roles. Uses UserPermissionResolver (2 queries) and cache; no Spatie load().
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $cacheKey = 'api_me_'.$user->id;
        $data = Cache::remember($cacheKey, self::TTL, function () use ($user) {
            $resolved = UserPermissionResolver::getRolesAndPermissions(
                (int) $user->id,
                $user->getMorphClass()
            );
            $prefs    = SystemPreference::singleton();
            $security = SecuritySetting::current();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $resolved['roles'],
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
            ->header('Cache-Control', 'private, max-age=60');
    }
}
