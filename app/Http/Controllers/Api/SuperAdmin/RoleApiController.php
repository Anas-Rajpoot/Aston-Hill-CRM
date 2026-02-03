<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\RolesPermissionsCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleApiController extends Controller
{
    public function __construct(
        private RolesPermissionsCacheService $cache
    ) {}

    /**
     * List all roles with users count and stats. Cached 5 min.
     */
    public function index(): JsonResponse
    {
        $result = $this->cache->getRolesList();
        return response()->json($result);
    }

    /**
     * Store a new role.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
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
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190', 'unique:roles,name,' . $role->id],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);
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
        return response()->json(['data' => $this->cache->getStructure()]);
    }

    /**
     * Single endpoint for role permissions page: structure + role + permission_names. One request, cached structure.
     */
    public function permissionsPageData(Role $role): JsonResponse
    {
        $payload = $this->cache->getPermissionsPageData((int) $role->id);
        return response()->json(['data' => $payload]);
    }

    /**
     * Get role with its permission ids (for permissions form). Kept for backward compatibility.
     */
    public function rolePermissions(Role $role): JsonResponse
    {
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
}
