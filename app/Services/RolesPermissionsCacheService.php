<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Cached roles and permissions data for super-admin UI.
 * Avoids N+1 and repeated queries; invalidate on role/permission changes.
 */
class RolesPermissionsCacheService
{
    private const CACHE_KEY_STRUCTURE = 'roles_permissions.structure';
    private const CACHE_KEY_ROLES_LIST = 'roles_permissions.roles_list';
    private const CACHE_KEY_PAGE_PREFIX = 'roles_permissions.page_';
    private const TTL_SECONDS = 3600; // 1 hour for structure (static until sync)
    private const TTL_ROLES = 300;   // 5 min for roles list
    private const TTL_PAGE = 600;    // 10 min for permissions-page payload per role

    public function getStructure(): array
    {
        return Cache::remember(self::CACHE_KEY_STRUCTURE, self::TTL_SECONDS, function () {
            return $this->buildStructure();
        });
    }

    public function getRolesList(): array
    {
        return Cache::remember(self::CACHE_KEY_ROLES_LIST, self::TTL_ROLES, function () {
            return $this->buildRolesList();
        });
    }

    /**
     * Single payload for the role permissions page: structure + role + permission_names + roles_list.
     * Cached per role (TTL 10 min); invalidated when role permissions are updated.
     * Includes roles_list so the frontend can fill the role dropdown with one request.
     */
    public function getPermissionsPageData(int $roleId): array
    {
        $cacheKey = self::CACHE_KEY_PAGE_PREFIX.$roleId;

        return Cache::remember($cacheKey, self::TTL_PAGE, function () use ($roleId) {
            $structure = $this->getStructure();
            $rolesListPayload = $this->getRolesList();

            $role = Role::query()
                ->select(['id', 'name'])
                ->where('id', $roleId)
                ->first();

            if (! $role) {
                return [
                    'structure' => $structure,
                    'role' => null,
                    'permission_names' => [],
                    'roles_list' => $rolesListPayload,
                ];
            }

            $permissionNames = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $roleId)
                ->where('permissions.guard_name', 'web')
                ->pluck('permissions.name')
                ->toArray();

            return [
                'structure' => $structure,
                'role' => ['id' => $role->id, 'name' => $role->name],
                'permission_names' => $permissionNames,
                'roles_list' => $rolesListPayload,
            ];
        });
    }

    public function forgetAll(): void
    {
        Cache::forget(self::CACHE_KEY_STRUCTURE);
        Cache::forget(self::CACHE_KEY_ROLES_LIST);
        \App\Http\Controllers\Api\BootstrapController::invalidate();
    }

    /** Forget cached permissions-page payload for one role (call after role permission sync). */
    public function forgetPermissionsPageForRole(int $roleId): void
    {
        Cache::forget(self::CACHE_KEY_PAGE_PREFIX.$roleId);
    }


    /**
     * Build structure from config; resolve permission ids with minimal queries (no firstOrCreate in loop).
     */
    private function buildStructure(): array
    {
        $structure = config('permissions.structure', []);
        $guard = 'web';

        $allNames = [];
        foreach ($structure as $moduleKey => $moduleDef) {
            foreach ($moduleDef['permissions'] ?? [] as $permDef) {
                $key = $permDef['key'] ?? '';
                $allNames[] = $moduleKey . '.' . $key;
            }
        }
        $allNames = array_values(array_unique(array_filter($allNames)));

        $existing = Permission::where('guard_name', $guard)
            ->whereIn('name', $allNames)
            ->get(['id', 'name'])
            ->keyBy('name');

        $missing = array_diff($allNames, $existing->keys()->toArray());
        if (count($missing) > 0) {
            $now = now();
            $inserts = [];
            foreach ($missing as $name) {
                $inserts[] = ['name' => $name, 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now];
            }
            Permission::insert($inserts);
            $newPerms = Permission::where('guard_name', $guard)->whereIn('name', $missing)->get(['id', 'name'])->keyBy('name');
            $existing = $existing->merge($newPerms);
        }

        $modules = [];
        foreach ($structure as $moduleKey => $moduleDef) {
            $permissions = [];
            foreach ($moduleDef['permissions'] ?? [] as $permDef) {
                $key = $permDef['key'] ?? '';
                $name = $moduleKey . '.' . $key;
                $perm = $existing->get($name);
                if (! $perm) {
                    continue;
                }
                $permissions[] = [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'label' => $permDef['label'] ?? $name,
                    'priority' => $permDef['priority'] ?? 'medium',
                ];
            }
            $modules[] = [
                'key' => $moduleKey,
                'label' => $moduleDef['label'] ?? $moduleKey,
                'icon' => $moduleDef['icon'] ?? 'folder',
                'permissions' => $permissions,
            ];
        }

        return $modules;
    }

    /**
     * Roles with users_count in one query (no N subqueries).
     */
    private function buildRolesList(): array
    {
        $userModel = \App\Models\User::class;

        $rows = Role::query()
            ->select([
                'roles.id',
                'roles.name',
                'roles.description',
                'roles.status',
                'roles.created_at',
                DB::raw('COUNT(model_has_roles.role_id) as users_count'),
            ])
            ->leftJoin('model_has_roles', function ($join) use ($userModel) {
                $join->on('model_has_roles.role_id', '=', 'roles.id')
                    ->where('model_has_roles.model_type', '=', $userModel);
            })
            ->groupBy('roles.id', 'roles.name', 'roles.description', 'roles.status', 'roles.created_at')
            ->orderBy('roles.name')
            ->get();

        $totalUsersAssigned = (int) $rows->sum('users_count');

        return [
            'data' => $rows->toArray(),
            'stats' => [
                'total_roles' => $rows->count(),
                'active_roles' => $rows->where('status', 'active')->count(),
                'total_users_assigned' => $totalUsersAssigned,
            ],
        ];
    }
}
