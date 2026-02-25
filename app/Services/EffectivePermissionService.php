<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EffectivePermissionService
{
    private const CACHE_PREFIX = 'rbac.user_effective_permissions.';
    private const CACHE_TTL = 600;

    public function userHasAny(User $user, array $permissionNames): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        $effective = $this->getEffectivePermissionNames($user);
        foreach ($permissionNames as $name) {
            if (isset($effective[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,true>
     */
    public function getEffectivePermissionNames(User $user): array
    {
        if ($user->hasRole('superadmin')) {
            return ['*' => true];
        }

        return Cache::remember(self::CACHE_PREFIX . $user->id, self::CACHE_TTL, function () use ($user) {
            $roleIds = $user->roles()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (empty($roleIds)) {
                return [];
            }

            $inheritance = app(RoleInheritanceService::class);
            $allRoleIds = [];
            foreach ($roleIds as $roleId) {
                $allRoleIds = array_merge($allRoleIds, $inheritance->resolveAncestorRoleIds($roleId));
            }
            $allRoleIds = array_values(array_unique(array_map('intval', $allRoleIds)));

            $names = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->whereIn('role_has_permissions.role_id', $allRoleIds)
                ->where('permissions.guard_name', 'web')
                ->pluck('permissions.name')
                ->all();

            $directNames = DB::table('model_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                ->where('model_has_permissions.model_type', User::class)
                ->where('model_has_permissions.model_id', (int) $user->id)
                ->where('permissions.guard_name', 'web')
                ->pluck('permissions.name')
                ->all();

            $names = array_values(array_unique(array_merge($names, $directNames)));

            $map = [];
            foreach ($names as $name) {
                $map[(string) $name] = true;
            }

            return $map;
        });
    }

    public function flushForUser(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . $userId);
    }

    public function flushAll(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();
        foreach ($userIds as $userId) {
            Cache::forget(self::CACHE_PREFIX . (int) $userId);
        }
    }
}
