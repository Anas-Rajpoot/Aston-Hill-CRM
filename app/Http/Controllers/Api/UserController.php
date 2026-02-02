<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamRoleMapping;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Notifications\UserApprovalStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $name = $request->get('name');
        $email = $request->get('email');
        $role = $request->get('role');
        $status = $request->get('status'); // single or comma-separated: approved,pending,rejected
        $createdFrom = $request->get('created_from');
        $createdTo = $request->get('created_to');
        $q = $request->get('q'); // legacy: search both name and email

        $query = User::query()
            ->with(['roles:id,name'])
            ->when(!$request->user()->hasRole('superadmin'), fn ($qq) => $qq->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')))
            ->when($name, fn ($qq) => $qq->where('name', 'like', "%{$name}%"))
            ->when($email, fn ($qq) => $qq->where('email', 'like', "%{$email}%"))
            ->when($q && !$name && !$email, fn ($qq) => $qq->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($status, function ($qq) use ($status) {
                $statuses = is_array($status) ? $status : array_map('trim', explode(',', $status));
                $qq->whereIn('status', $statuses);
            })
            ->when($role, fn ($qq) => $qq->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->when($createdFrom, fn ($qq) => $qq->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo, fn ($qq) => $qq->whereDate('created_at', '<=', $createdTo))
            ->latest();

        $users = $query->clone()->paginate($request->get('per_page', 10));
        $roles = Role::orderBy('name')->get(['id', 'name']);

        $statsQuery = User::query();
        if (!$request->user()->hasRole('superadmin')) {
            $statsQuery->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin'));
        }
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('status', 'approved')->count(),
            'inactive' => (clone $statsQuery)->where('status', 'rejected')->count(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
        ];

        $userIds = collect($users->items())->pluck('id')->toArray();
        $lastLogins = collect();
        if (!empty($userIds)) {
            $lastLogins = UserLoginLog::query()
                ->selectRaw('user_id, MAX(login_at) as login_at')
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $items = collect($users->items())->map(function ($u) use ($lastLogins) {
            $log = $lastLogins->get($u->id);
            $u->last_login_at = $log?->login_at ? (is_object($log->login_at) ? $log->login_at->format('c') : (string) $log->login_at) : null;
            return $u;
        });

        return response()->json([
            'users' => $items,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'stats' => $stats,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:15'],
            'country' => ['nullable', 'string', 'max:100'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        if (($validated['status'] ?? '') === 'approved') {
            $request->validate(['roles' => ['required', 'array', 'min:1']], ['roles.required' => 'Select at least one role when status is Approved.']);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'country' => $validated['country'] ?? null,
            'status' => $validated['status'] ?? 'pending',
        ]);

        if (!empty($validated['roles'])) {
            $roles = Role::whereIn('id', $validated['roles'])->get();
            $user->syncRoles($roles);
        }
        if (($validated['status'] ?? '') === 'approved') {
            $user->approved_by = auth()->id();
            $user->approved_at = now();
            $user->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user->load('roles'),
        ], 201);
    }

    public function bulkActivate(Request $request): JsonResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:users,id']]);
        $count = User::whereIn('id', $validated['ids'])->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json(['message' => "{$count} user(s) activated.", 'count' => $count]);
    }

    public function bulkDeactivate(Request $request): JsonResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:users,id']]);
        $count = User::whereIn('id', $validated['ids'])
            ->update(['status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now()]);
        User::whereIn('id', $validated['ids'])->each(fn ($u) => $u->syncRoles([]));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json(['message' => "{$count} user(s) deactivated.", 'count' => $count]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles:id,name');
        $lastLog = UserLoginLog::where('user_id', $user->id)->orderByDesc('login_at')->first();
        $user->last_login_at = $lastLog?->login_at ? (is_object($lastLog->login_at) ? $lastLog->login_at->format('c') : (string) $lastLog->login_at) : null;
        $roles = Role::orderBy('name')->get(['id', 'name']);
        $mappings = TeamRoleMapping::allMappings();
        $managerRoleId = TeamRoleMapping::roleIdFor('manager');
        $teamLeaderRoleId = TeamRoleMapping::roleIdFor('team_leader');
        $salesAgentRoleId = TeamRoleMapping::roleIdFor('sales_agent');
        $managerRole = $managerRoleId ? Role::find($managerRoleId) : null;
        $teamLeaderRole = $teamLeaderRoleId ? Role::find($teamLeaderRoleId) : null;
        $managers = $managerRole
            ? User::role($managerRole)->where('status', 'approved')->where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name', 'email'])
            : collect();
        $teamLeaders = $teamLeaderRole
            ? User::role($teamLeaderRole)->where('status', 'approved')->where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name', 'email', 'manager_id'])
            : collect();
        return response()->json([
            'user' => $user,
            'roles' => $roles,
            'managers' => $managers,
            'team_leaders' => $teamLeaders,
            'team_leader_role_id' => $teamLeaderRoleId,
            'sales_agent_role_id' => $salesAgentRoleId,
            'manager_label' => $mappings['manager']['label'] ?? 'Manager',
            'team_leader_label' => $mappings['team_leader']['label'] ?? 'Team Leader',
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:15'],
            'country' => ['nullable', 'string', 'max:100'],
            'cnic_number' => ['nullable', 'string', 'max:20'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user->fill(collect($validated)->except(['password', 'roles', 'manager_id', 'team_leader_id'])->toArray());

        if (array_key_exists('roles', $validated) && !empty($validated['roles'])) {
            $request->validate(
                ['roles' => ['required', 'array', 'min:1']],
                ['roles.required' => 'Please select at least one role.']
            );
            $mappings = TeamRoleMapping::allMappings();
            $teamLeaderRoleId = TeamRoleMapping::roleIdFor('team_leader');
            $salesAgentRoleId = TeamRoleMapping::roleIdFor('sales_agent');
            $selectedRoleIds = array_map('intval', $validated['roles'] ?? []);
            $managerLabel = $mappings['manager']['label'] ?? 'Manager';
            $teamLeaderLabel = $mappings['team_leader']['label'] ?? 'Team Leader';
            if ($teamLeaderRoleId !== null && in_array($teamLeaderRoleId, $selectedRoleIds, true)) {
                $request->validate(
                    ['manager_id' => ['required', 'integer', 'exists:users,id']],
                    ['manager_id.required' => "Please select a {$managerLabel} when assigning this role."]
                );
            }
            if ($salesAgentRoleId !== null && in_array($salesAgentRoleId, $selectedRoleIds, true)) {
                $request->validate(
                    [
                        'manager_id' => ['required', 'integer', 'exists:users,id'],
                        'team_leader_id' => ['required', 'integer', 'exists:users,id'],
                    ],
                    [
                        'manager_id.required' => "Please select a {$managerLabel}.",
                        'team_leader_id.required' => "Please select a {$teamLeaderLabel}.",
                    ]
                );
            }
            $user->status = 'approved';
            $user->approved_by = auth()->id();
            $user->approved_at = now();
            $user->rejected_by = null;
            $user->rejected_at = null;
            $user->rejection_reason = null;
            $statusChanged = $user->getOriginal('status') !== 'approved';
        } else {
            $statusChanged = false;
        }

        $user->save();

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->save();
        }

        if (array_key_exists('roles', $validated) && $user->status !== 'rejected') {
            $roles = Role::whereIn('id', $validated['roles'] ?? [])->get();
            $user->syncRoles($roles);
            $teamLeaderRoleId = TeamRoleMapping::roleIdFor('team_leader');
            $salesAgentRoleId = TeamRoleMapping::roleIdFor('sales_agent');
            $selectedRoleIds = $roles->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            $hasTeamLeader = $teamLeaderRoleId !== null && in_array($teamLeaderRoleId, $selectedRoleIds, true);
            $hasSalesAgent = $salesAgentRoleId !== null && in_array($salesAgentRoleId, $selectedRoleIds, true);
            if ($hasSalesAgent && !empty($validated['manager_id']) && !empty($validated['team_leader_id'])) {
                $user->manager_id = (int) $validated['manager_id'];
                $user->team_leader_id = (int) $validated['team_leader_id'];
            } elseif ($hasTeamLeader && !empty($validated['manager_id'])) {
                $user->manager_id = (int) $validated['manager_id'];
                $user->team_leader_id = null;
            } else {
                $user->manager_id = null;
                $user->team_leader_id = null;
            }
            $user->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($statusChanged) {
            $user->notify(new UserApprovalStatusNotification('active'));
        }

        return response()->json(['message' => 'User updated successfully.', 'user' => $user->load('roles')]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'User deleted successfully.']);
    }
}
