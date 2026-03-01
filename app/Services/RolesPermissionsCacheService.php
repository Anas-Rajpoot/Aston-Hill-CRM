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
    /** System-only modules are not assignable from Roles & Permissions UI. */
    private const EXCLUDED_PERMISSION_MODULES = [
        'system',
        'settings',
        'attendance',
        'roles',
        'notification_rules',
        'permissions',
    ];

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

            $inheritedPermissionNames = $this->resolveInheritedPermissionNamesForRole($roleId);
            $effectivePermissionNames = array_values(array_unique(array_merge($permissionNames, $inheritedPermissionNames)));

            $parentRoleIds = DB::table('role_inheritance')
                ->where('child_role_id', $roleId)
                ->pluck('parent_role_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return [
                'structure' => $structure,
                'role' => ['id' => $role->id, 'name' => $role->name],
                'permission_names' => $permissionNames,
                'inherited_permission_names' => $inheritedPermissionNames,
                'effective_permission_names' => $effectivePermissionNames,
                'parent_role_ids' => $parentRoleIds,
                'roles_list' => $rolesListPayload,
            ];
        });
    }

    public function forgetAll(): void
    {
        Cache::forget(self::CACHE_KEY_STRUCTURE);
        Cache::forget(self::CACHE_KEY_ROLES_LIST);
        \App\Http\Controllers\Api\BootstrapController::invalidate();
        app(RoleInheritanceService::class)->flushAll();
        app(EffectivePermissionService::class)->flushAll();
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

        $modules = [];
        foreach ($structure as $moduleKey => $moduleDef) {
            if (in_array((string) $moduleKey, self::EXCLUDED_PERMISSION_MODULES, true)) {
                continue;
            }
            $permissions = [];
            foreach ($moduleDef['permissions'] ?? [] as $permDef) {
                $key = $permDef['key'] ?? '';
                if (! is_string($key) || trim($key) === '') {
                    continue;
                }
                $name = $moduleKey . '.' . $key;
                $perm = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => $guard,
                ]);
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

    private function isExcludedModule(string $moduleKey): bool
    {
        return in_array((string) $moduleKey, self::EXCLUDED_PERMISSION_MODULES, true);
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
            ->where('roles.guard_name', '=', 'web')
            ->groupBy('roles.id', 'roles.name', 'roles.description', 'roles.status', 'roles.created_at')
            ->orderBy('roles.name')
            ->orderBy('roles.id')
            ->get();

        // One row per role name: if DB has duplicate names (e.g. from before unique constraint), keep one and sum users
        $byName = [];
        foreach ($rows as $row) {
            $name = $row->name;
            $r = (object) [
                'id' => $row->id,
                'name' => $row->name,
                'description' => $row->description,
                'status' => $row->status,
                'created_at' => $row->created_at,
                'users_count' => (int) $row->users_count,
            ];
            if (! isset($byName[$name])) {
                $byName[$name] = $r;
            } else {
                $byName[$name]->users_count += (int) $row->users_count;
                // Keep the row with the earliest id (original role)
                if ($row->id < $byName[$name]->id) {
                    $byName[$name]->id = $row->id;
                    $byName[$name]->description = $row->description;
                    $byName[$name]->status = $row->status;
                    $byName[$name]->created_at = $row->created_at;
                }
            }
        }
        $uniqueByName = collect(array_values($byName))->sortBy('name')->values();

        $totalUsersAssigned = (int) $uniqueByName->sum('users_count');

        return [
            'data' => $uniqueByName->toArray(),
            'stats' => [
                'total_roles' => $uniqueByName->count(),
                'active_roles' => $uniqueByName->where('status', 'active')->count(),
                'total_users_assigned' => $totalUsersAssigned,
            ],
        ];
    }

    /**
     * @return array<int,string>
     */
    private function resolveInheritedPermissionNamesForRole(int $roleId): array
    {
        $ancestorRoleIds = app(RoleInheritanceService::class)->resolveAncestorRoleIds($roleId);
        $ancestorRoleIds = array_values(array_filter(
            array_map('intval', $ancestorRoleIds),
            fn (int $id) => $id !== $roleId
        ));

        if (empty($ancestorRoleIds)) {
            return [];
        }

        return DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('role_has_permissions.role_id', $ancestorRoleIds)
            ->where('permissions.guard_name', 'web')
            ->pluck('permissions.name')
            ->unique()
            ->values()
            ->all();
    }
}
