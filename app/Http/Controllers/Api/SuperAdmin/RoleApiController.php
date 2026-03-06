<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\RoleInheritanceService;
use App\Services\RolesPermissionsCacheService;
use App\Support\RbacPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleApiController extends Controller
{
    public function __construct(
        private RolesPermissionsCacheService $cache,
        private RoleInheritanceService $inheritance
    ) {}

    /**
     * List all roles with users count and stats. Cached 5 min.
     */
    public function index(): JsonResponse
    {
        $this->authorizeRoleRead(request()->user());

        $result = $this->cache->getRolesList();
        return response()->json($result);
    }

    /**
     * Store a new role.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeRolePermissionAssignment($request->user());

        $request->merge([
            'name' => $this->normalizeRoleName((string) $request->input('name', '')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ], [
            'name.regex' => 'Role name must use lowercase letters, numbers, and underscores only (e.g. sales_agent).',
        ]);
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
        $this->cache->forgetAll();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json(['data' => $role], 201);
    }

    /**
     * Show a role (for edit form). No permission load needed.
     */
    public function show(Role $role): JsonResponse
    {
        $this->authorizeRoleRead(request()->user());

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description ?? null,
                'status' => $role->status ?? 'active',
            ],
        ]);
    }

    /**
     * Update a role.
     * Role name change updates the single row in roles table; permissions and user assignments
     * are linked by role_id, so they stay with the role. Do not rename system roles (e.g. superadmin)
     * because application code checks them by name (hasRole('superadmin'), etc.).
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorizeRolePermissionAssignment($request->user());

        $request->merge([
            'name' => $this->normalizeRoleName((string) $request->input('name', '')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,name,' . $role->id],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ], [
            'name.regex' => 'Role name must use lowercase letters, numbers, and underscores only (e.g. sales_agent).',
        ]);

        // Prevent renaming system role used by middleware, policies, and Blade directives
        if ($role->name === 'superadmin' && $data['name'] !== 'superadmin') {
            return response()->json([
                'message' => 'The superadmin role name cannot be changed. It is used by the application for access control.',
            ], 422);
        }

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? $role->description,
            'status' => $data['status'] ?? $role->status,
        ]);
        $this->cache->forgetAll();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json(['data' => $role]);
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->authorizeRolePermissionAssignment(request()->user());

        if ($role->name === 'superadmin') {
            return response()->json(['message' => 'Cannot delete superadmin role.'], 422);
        }
        $role->delete();
        $this->cache->forgetAll();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json(['message' => 'Role deleted.']);
    }

    /**
     * Get permission structure (modules with permissions). Cached 1 hour; bulk DB resolution.
     */
    public function permissionsStructure(): JsonResponse
    {
        $this->authorizeRoleRead(request()->user());

        return response()->json(['data' => $this->cache->getStructure()]);
    }

    /**
     * Single endpoint for role permissions page: structure + role + permission_names. One request, cached structure.
     */
    public function permissionsPageData(Role $role): JsonResponse
    {
        $this->authorizeRoleRead(request()->user());

        $payload = $this->cache->getPermissionsPageData((int) $role->id);
        return response()->json(['data' => $payload]);
    }

    /**
     * Get role with its permission ids (for permissions form). Kept for backward compatibility.
     */
    public function rolePermissions(Role $role): JsonResponse
    {
        $this->authorizeRoleRead(request()->user());

        $payload = $this->cache->getPermissionsPageData((int) $role->id);
        return response()->json([
            'data' => [
                'role' => $payload['role'],
                'permission_ids' => [], // not used by Vue when using permission_names
                'permission_names' => $payload['permission_names'],
            ],
        ]);
    }

    /**
     * Update role permissions (sync by permission names).
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorizeRolePermissionAssignment($request->user());

        if ($role->name === 'superadmin') {
            return response()->json(['message' => 'Superadmin permissions are managed by the system.'], 422);
        }
        $data = $request->validate([
            'permission_names' => ['nullable', 'array'],
            'permission_names.*' => ['string', 'max:190'],
        ]);
        $names = array_values(array_unique(array_filter($data['permission_names'] ?? [])));
        $validNames = Permission::whereIn('name', $names)->where('guard_name', 'web')->pluck('name')->toArray();

        $role->syncPermissions($validNames);
        $this->cache->forgetPermissionsPageForRole((int) $role->id);
        $this->cache->forgetAll();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json([
            'message' => 'Role permissions updated.',
            'data' => ['permission_names' => $validNames],
        ]);
    }

    public function addParentRole(Request $request, Role $role): JsonResponse
    {
        $this->authorizeRolePermissionAssignment($request->user());

        if ($role->name === 'superadmin') {
            return response()->json(['message' => 'Superadmin role cannot inherit other roles.'], 422);
        }

        $data = $request->validate([
            'parent_role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $parent = Role::query()->where('id', (int) $data['parent_role_id'])->firstOrFail();
        if ($parent->name === 'superadmin') {
            return response()->json(['message' => 'Roles cannot inherit superadmin capabilities.'], 422);
        }

        $this->inheritance->addEdge($parent, $role);
        $this->cache->forgetAll();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Parent role assigned successfully.']);
    }

    public function removeParentRole(Request $request, Role $role, Role $parentRole): JsonResponse
    {
        $this->authorizeRolePermissionAssignment($request->user());

        $this->inheritance->removeEdge($parentRole, $role);
        $this->cache->forgetAll();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Parent role removed successfully.']);
    }

    private function authorizeRolePermissionAssignment($user): void
    {
        if (! $user || ! RbacPermission::can($user, 'roles', 'assign_permissions', [
            'roles.manage_permissions',
            'roles.assign_permissions',
        ])) {
            abort(403, 'Unauthorized');
        }
    }

    private function authorizeRoleRead($user): void
    {
        if (! $user || ! RbacPermission::can($user, 'roles', 'read', [
            'roles.list',
            'roles.view',
        ])) {
            abort(403, 'Unauthorized');
        }
    }

    private function normalizeRoleName(string $name): string
    {
        $value = strtolower(trim($name));
        $value = preg_replace('/[\s\-]+/', '_', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? $value;
        $value = preg_replace('/_+/', '_', $value) ?? $value;
        return trim($value, '_');
    }
}
