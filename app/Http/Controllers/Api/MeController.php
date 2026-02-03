<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $resolved['roles'],
            ];
        });

        return response()
            ->json($data)
            ->header('Cache-Control', 'private, max-age=60');
    }
}
