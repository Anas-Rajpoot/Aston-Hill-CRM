<?php

namespace App\Http\Controllers;

use App\Models\FieldSubmission;
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
     * Uses dynamic roles from config – super admin can add/edit/delete roles.
     */
    public function teamOptions(Request $request): JsonResponse
    {
        $roleKeys = config('field_submissions.team_roles', [
            'manager' => 'manager',
            'team_leader' => 'team_leader',
            'sales_agent' => 'sales_agent',
        ]);

        $formatUsers = function ($users) {
            return $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'label' => $user->name . ' (' . $user->email . ')',
            ])->values()->all();
        };

        $managers = collect();
        $teamLeaders = collect();
        $salesAgents = collect();

        foreach ($roleKeys as $key => $roleName) {
            if (empty($roleName)) {
                continue;
            }
            try {
                $users = User::role($roleName)->orderBy('name')->get(['id', 'name', 'email']);
                if ($key === 'manager') {
                    $managers = $users;
                } elseif ($key === 'team_leader') {
                    $teamLeaders = $users;
                } elseif ($key === 'sales_agent') {
                    $salesAgents = $users;
                }
            } catch (\Throwable $e) {
                Log::debug("Field submission team role '{$roleName}' not found or error: " . $e->getMessage());
            }
        }

        return response()->json([
            'managers' => $formatUsers($managers),
            'team_leaders' => $formatUsers($teamLeaders),
            'sales_agents' => $formatUsers($salesAgents),
        ]);
    }
}
