<?php

namespace App\Http\Controllers;

use App\Models\FieldSubmission;
use App\Models\TeamRoleMapping;
use App\Models\User;
use App\Services\FieldSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FieldSubmissionController extends Controller
{
    public function __construct(private FieldSubmissionService $fieldSubmissionService)
    {
    }

    /**
     * Store a new field submission (draft or submit in one step).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50'],
            'product' => ['required', 'string', 'max:255'],
            'alternate_number' => ['required', 'string', 'max:50'],
            'emirates' => ['required', 'string', 'max:100'],
            'location_coordinates' => ['nullable', 'string', 'max:100'],
            'complete_address' => ['required', 'string', 'max:1000'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'special_instruction' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['required', 'exists:users,id'],
            'team_leader_id' => ['required', 'exists:users,id'],
            'sales_agent_id' => ['required', 'exists:users,id'],
        ], [
            'company_name.required' => 'Company name is required.',
            'contact_number.required' => 'Contact number is required.',
            'product.required' => 'Product is required.',
            'alternate_number.required' => 'Alternate number is required.',
            'emirates.required' => 'Emirates is required.',
            'complete_address.required' => 'Complete address is required.',
            'manager_id.required' => 'Please select a manager.',
            'team_leader_id.required' => 'Please select a team leader.',
            'sales_agent_id.required' => 'Please select a sales agent.',
        ]);

        $fieldSubmission = $this->fieldSubmissionService->create($data, $request->user()->id);

        if ($request->boolean('submit')) {
            $this->fieldSubmissionService->submit($fieldSubmission);
        }

        return response()->json([
            'id' => $fieldSubmission->id,
            'message' => $request->boolean('submit')
                ? 'Field request submitted successfully.'
                : 'Field submission saved.',
        ], 201);
    }

    /**
     * Return users for Manager, Team Leader, Sales Agent dropdowns.
     * Uses dynamic team_role_mappings – super admin can change role names/assignments without effect.
     */
    public function teamOptions(Request $request): JsonResponse
    {
        $formatUsers = function ($users, bool $includeHierarchy = false) {
            return $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'label' => $user->name . ' (' . $user->email . ')',
                ...($includeHierarchy ? [
                    'manager_id' => $user->manager_id ?? $user->teamLeader?->manager_id ?? null,
                    'team_leader_id' => $user->team_leader_id ?? null,
                ] : []),
            ])->values()->all();
        };

        // Get role_ids from DB; fallback to config (role names) if no mappings yet
        $mappings = $this->resolveTeamRoleMappings();

        $managers = collect();
        $teamLeaders = collect();
        $salesAgents = collect();
        $labels = ['manager' => 'Manager', 'team_leader' => 'Team Leader', 'sales_agent' => 'Sales Agent'];

        foreach (['manager', 'team_leader', 'sales_agent'] as $slotKey) {
            $roleId = $mappings[$slotKey]['role_id'] ?? null;
            if (!$roleId) {
                continue;
            }
            try {
                $role = \Spatie\Permission\Models\Role::find($roleId);
                if (!$role) continue;
                $users = User::role($role)->with(['teamLeader:id,manager_id', 'manager:id'])
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'manager_id', 'team_leader_id']);

                $labels[$slotKey] = $mappings[$slotKey]['label'] ?? ucfirst(str_replace('_', ' ', $slotKey));

                if ($slotKey === 'manager') {
                    $managers = $users;
                } elseif ($slotKey === 'team_leader') {
                    $teamLeaders = $users;
                } elseif ($slotKey === 'sales_agent') {
                    $salesAgents = $users;
                }
            } catch (\Throwable $e) {
                Log::debug("Team role slot '{$slotKey}' role_id {$roleId} error: " . $e->getMessage());
            }
        }

        return response()->json([
            'managers' => $formatUsers($managers, true),
            'team_leaders' => $formatUsers($teamLeaders, true),
            'sales_agents' => $formatUsers($salesAgents, true),
            'labels' => $labels,
        ]);
    }

    /**
     * Resolve team role mappings: DB first, then config fallback.
     * Returns [manager => [role_id, label], team_leader => [...], sales_agent => [...]]
     */
    private function resolveTeamRoleMappings(): array
    {
        $dbMappings = TeamRoleMapping::with('role')->get()->keyBy('slot_key');
        $configRoles = config('field_submissions.team_roles', [
            'manager' => 'manager',
            'team_leader' => 'team_leader',
            'sales_agent' => 'sales_agent',
        ]);

        $result = [];
        foreach (['manager', 'team_leader', 'sales_agent'] as $slotKey) {
            $db = $dbMappings->get($slotKey);
            if ($db && $db->role_id) {
                $result[$slotKey] = [
                    'role_id' => $db->role_id,
                    'label' => $db->role?->name ?? ucfirst(str_replace('_', ' ', $slotKey)),
                ];
            } else {
                $roleName = $configRoles[$slotKey] ?? null;
                if ($roleName) {
                    $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
                    if ($role) {
                        $result[$slotKey] = ['role_id' => $role->id, 'label' => $role->name];
                    }
                }
            }
        }
        return $result;
    }
}
