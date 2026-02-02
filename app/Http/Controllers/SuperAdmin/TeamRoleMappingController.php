<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TeamRoleMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class TeamRoleMappingController extends Controller
{
    /**
     * Get current team role mappings (for Settings > Team Hierarchy).
     */
    public function index(): JsonResponse
    {
        $mappings = TeamRoleMapping::with('role')->orderBy('sort_order')->get();
        $roles = Role::where('name', '!=', 'superadmin')->orderBy('name')->get(['id', 'name']);

        $slots = [
            'manager' => null,
            'team_leader' => null,
            'sales_agent' => null,
        ];

        foreach ($mappings as $m) {
            $slots[$m->slot_key] = [
                'role_id' => $m->role_id,
                'role_name' => $m->role?->name,
                'label' => $m->role?->name ?? ucfirst(str_replace('_', ' ', $m->slot_key)),
            ];
        }

        return response()->json([
            'mappings' => $slots,
            'roles' => $roles,
            'slot_labels' => [
                'manager' => 'Top-level hierarchy role (e.g. Manager, Department Head)',
                'team_leader' => 'Middle hierarchy role (e.g. Team Leader, Supervisor)',
                'sales_agent' => 'Bottom hierarchy role (e.g. Sales Agent, Representative)',
            ],
        ]);
    }

    /**
     * Update team role mappings. Super admin only.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'manager' => ['nullable', 'integer', 'exists:roles,id'],
            'team_leader' => ['nullable', 'integer', 'exists:roles,id'],
            'sales_agent' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        TeamRoleMapping::updateMappings($data);

        return response()->json(['message' => 'Team hierarchy roles updated.']);
    }
}
