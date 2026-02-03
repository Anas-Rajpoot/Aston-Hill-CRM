<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolve user roles and permission names with a fixed number of queries (no Spatie getAllPermissions).
 * Used by BootstrapController to avoid N+1 and heavy Spatie internals.
 */
class UserPermissionResolver
{
    private const GUARD = 'web';

    /**
     * Get role names and permission names for a user in 2 queries.
     * Does not use Spatie's getAllPermissions() or role->permissions.
     *
     * @return array{roles: list<string>, permissions: list<string>}
     */
    public static function getRolesAndPermissions(int $userId, string $modelType): array
    {
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $modelType)
            ->where('roles.guard_name', self::GUARD)
            ->pluck('roles.name')
            ->filter()
            ->values()
            ->all();

        // Permissions: via roles + direct model_has_permissions. Single query with UNION.
        $permissionNames = DB::select(
            "
            (SELECT p.name
             FROM role_has_permissions rhp
             INNER JOIN model_has_roles mhr ON mhr.role_id = rhp.role_id
                 AND mhr.model_id = ? AND mhr.model_type = ?
             INNER JOIN permissions p ON p.id = rhp.permission_id
             WHERE p.guard_name = ?)
            UNION
            (SELECT p.name
             FROM model_has_permissions mhp
             INNER JOIN permissions p ON p.id = mhp.permission_id
             WHERE mhp.model_id = ? AND mhp.model_type = ? AND p.guard_name = ?)
            ",
            [$userId, $modelType, self::GUARD, $userId, $modelType, self::GUARD]
        );

        $permissions = array_values(array_unique(array_map(fn ($row) => $row->name, $permissionNames)));

        return ['roles' => $roles, 'permissions' => $permissions];
    }
}
