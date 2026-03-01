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

        // Permissions: via roles + direct model_has_permissions. Cross-DB union query.
        $viaRoles = DB::table('role_has_permissions as rhp')
            ->join('model_has_roles as mhr', function ($join) use ($userId, $modelType) {
                $join->on('mhr.role_id', '=', 'rhp.role_id')
                    ->where('mhr.model_id', $userId)
                    ->where('mhr.model_type', $modelType);
            })
            ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
            ->where('p.guard_name', self::GUARD)
            ->select('p.name');

        $direct = DB::table('model_has_permissions as mhp')
            ->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
            ->where('mhp.model_id', $userId)
            ->where('mhp.model_type', $modelType)
            ->where('p.guard_name', self::GUARD)
            ->select('p.name');

        $permissions = $viaRoles
            ->union($direct)
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ['roles' => $roles, 'permissions' => $permissions];
    }
}
