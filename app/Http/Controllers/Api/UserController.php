<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\TeamRoleMapping;
use App\Models\User;
use App\Models\UserAudit;
use App\Models\UserColumnPreference;
use App\Models\UserLoginLog;
use App\Notifications\UserApprovalStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    private const MODULE = 'users';

    private const ALLOWED_COLUMNS = [
        'id', 'name', 'email', 'phone', 'country', 'roles', 'status', 'last_login_at',
        'created_at', 'employee_number', 'department', 'extension', 'joining_date', 'terminate_date',
        'manager', 'team_leader',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager_id', 'team_leader_id']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
            'name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'string', 'max:200'],
            'role' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string'], // comma-separated or single
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'created_from' => ['sometimes', 'nullable', 'date'],
            'created_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:created_from'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $requestUser = $request->user();
        $columns = $this->resolveColumns($requestUser, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? config('modules.users.default_sort.0', 'name');
        $order = strtolower($validated['order'] ?? config('modules.users.default_sort.1', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = User::query()
            ->with(['roles:id,name', 'manager:id,name', 'teamLeader:id,name'])
            ->when(! $requestUser->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')));

        $this->applyFilters($query, $validated);
        $total = $query->count();

        $this->applySort($query, $sort, $order);
        $users = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $userIds = $users->pluck('id')->toArray();
        $lastLogins = collect();
        if (! empty($userIds)) {
            $lastLogins = UserLoginLog::query()
                ->selectRaw('user_id, MAX(login_at) as login_at')
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);
        $statsQuery = User::query()
            ->when(! $requestUser->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')));
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('status', 'approved')->count(),
            'inactive' => (clone $statsQuery)->where('status', 'rejected')->count(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
        ];

        $items = $users->map(function ($u) use ($lastLogins, $columns) {
            $log = $lastLogins->get($u->id);
            $lastLoginAt = $log?->login_at ? (is_object($log->login_at) ? $log->login_at->format('c') : (string) $log->login_at) : null;
            $row = [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'country' => $u->country,
                'roles' => $u->roles->pluck('name')->values()->all(),
                'status' => $u->status,
                'last_login_at' => $lastLoginAt,
                'created_at' => $u->created_at?->toIso8601String(),
                'employee_number' => $u->employee_number,
                'department' => $u->department,
                'extension' => $u->extension,
                'joining_date' => $u->joining_date?->format('Y-m-d'),
                'terminate_date' => $u->terminate_date?->format('Y-m-d'),
                'manager' => $u->manager?->name,
                'team_leader' => $u->teamLeader?->name,
            ];
            return array_intersect_key($row, array_fill_keys(array_merge(['id'], $columns), true));
        });

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return response()->json([
            'users' => $items,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'stats' => $stats,
            'roles' => $roles,
        ]);
    }

    private function resolveColumns($user, ?array $requestColumns): array
    {
        if (! empty($requestColumns)) {
            return array_values(array_unique(array_intersect($requestColumns, self::ALLOWED_COLUMNS)));
        }
        $cacheKey = 'col_pref_' . $user->id . '_' . self::MODULE;
        $preference = Cache::remember($cacheKey, 3600, function () use ($user) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', self::MODULE)
                ->first();
        });
        $cols = $preference?->visible_columns ?? config('modules.users.default_columns', []);
        $cols = is_array($cols) ? $cols : [];

        return array_values(array_intersect($cols, self::ALLOWED_COLUMNS));
    }

    private function applyFilters($query, array $validated): void
    {
        if (! empty($validated['name'])) {
            $term = '%' . addcslashes($validated['name'], '%_\\') . '%';
            $query->where('name', 'like', $term);
        }
        if (! empty($validated['email'])) {
            $term = '%' . addcslashes($validated['email'], '%_\\') . '%';
            $query->where('email', 'like', $term);
        }
        if (! empty($validated['q']) && empty($validated['name']) && empty($validated['email'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }
        if (! empty($validated['status'])) {
            $statuses = is_array($validated['status']) ? $validated['status'] : array_map('trim', explode(',', $validated['status']));
            $query->whereIn('status', $statuses);
        }
        if (! empty($validated['role'])) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $validated['role']));
        }
        if (! empty($validated['country'])) {
            $term = '%' . addcslashes($validated['country'], '%_\\') . '%';
            $query->where('country', 'like', $term);
        }
        if (! empty($validated['created_from'])) {
            $query->whereDate('created_at', '>=', $validated['created_from']);
        }
        if (! empty($validated['created_to'])) {
            $query->whereDate('created_at', '<=', $validated['created_to']);
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        $direction = $order === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['manager', 'team_leader'], true)) {
            $col = $sort === 'manager' ? 'manager_id' : 'team_leader_id';
            $alias = $sort . '_u';
            $query->leftJoin('users as ' . $alias, 'users.' . $col, '=', $alias . '.id')
                ->orderBy($alias . '.name', $direction)
                ->select('users.*');
            return;
        }
        if ($sort === 'last_login_at') {
            $sub = UserLoginLog::query()->selectRaw('user_id, MAX(login_at) as login_at')->groupBy('user_id');
            $query->leftJoinSub($sub, 'last_logins', 'users.id', '=', 'last_logins.user_id')
                ->orderBy('last_logins.login_at', $direction)
                ->select('users.*');
            return;
        }
        $query->orderBy('users.' . $sort, $direction);
    }

    public function filters(Request $request): JsonResponse
    {
        $user = $request->user();
        $baseQuery = User::query()
            ->when(! $user->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')));
        $roles = Role::orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['value' => $r->name, 'label' => $r->name])->all();

        return response()->json([
            'statuses' => [
                ['value' => 'approved', 'label' => 'Active'],
                ['value' => 'rejected', 'label' => 'Inactive'],
                ['value' => 'pending', 'label' => 'Pending Approval'],
            ],
            'roles' => $roles,
        ]);
    }

    public function columns(Request $request): JsonResponse
    {
        $config = config('modules.users.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            $allColumns[] = ['key' => $key, 'label' => $def['label'] ?? $key];
        }
        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', self::MODULE)
            ->first();
        $visible = $pref?->visible_columns ?? config('modules.users.default_columns', []);

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
        ]);
        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );
        Cache::forget('col_pref_' . $request->user()->id . '_' . self::MODULE);

        return response()->json(['success' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('superadmin') && ! $request->user()->can('users.edit') && ! $request->user()->can('users.create')) {
            return response()->json(['message' => 'You do not have permission to add users.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:15'],
            'country' => ['nullable', 'string', 'max:100'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:users,employee_number'],
            'department' => ['nullable', 'string', 'max:100'],
            'extension' => ['nullable', 'string', 'max:20'],
            'joining_date' => ['nullable', 'date'],
            'terminate_date' => ['nullable', 'date'],
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
            'employee_number' => $validated['employee_number'] ?? null,
            'department' => $validated['department'] ?? null,
            'extension' => $validated['extension'] ?? null,
            'joining_date' => $validated['joining_date'] ?? null,
            'terminate_date' => $validated['terminate_date'] ?? null,
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
        $ids = collect($validated['ids']);
        $superAdminIds = User::whereIn('id', $ids)->whereHas('roles', fn ($r) => $r->where('name', 'superadmin'))->pluck('id');
        $idsToDeactivate = $ids->diff($superAdminIds)->values()->all();
        $count = User::whereIn('id', $idsToDeactivate)
            ->update(['status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now()]);
        // Roles are preserved on deactivation; only super admin / authorized user can change roles explicitly.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json(['message' => "{$count} user(s) deactivated.", 'count' => $count]);
    }

    public function show(User $user): JsonResponse
    {
        if ($user->hasRole('superadmin') && request()->user()->id !== $user->id) {
            return response()->json(['message' => 'Only the super admin can view this account.'], 403);
        }

        $user->load('roles:id,name');
        $lastLog = UserLoginLog::where('user_id', $user->id)->orderByDesc('login_at')->first();
        $user->last_login_at = $lastLog?->login_at ? (is_object($lastLog->login_at) ? $lastLog->login_at->format('c') : (string) $lastLog->login_at) : null;
        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);
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

    /**
     * Critical data for initial render only (edit page above-the-fold).
     * Eager load roles; single last_login query; optional Cache::remember (Redis 5–15 min TTL).
     */
    public function prime(User $user): JsonResponse
    {
        if ($user->hasRole('superadmin') && request()->user()->id !== $user->id) {
            return response()->json(['message' => 'Only the super admin can view this account.'], 403);
        }

        $ttl = (int) (config('cache.user_prime_ttl', 300)); // 5 min default
        $cacheKey = 'user.prime.' . $user->id;

        $data = Cache::remember($cacheKey, $ttl, function () use ($user) {
            $user->load(['roles:id,name']);
            $lastLog = UserLoginLog::where('user_id', $user->id)->orderByDesc('login_at')->first();
            $user->last_login_at = $lastLog?->login_at
                ? (is_object($lastLog->login_at) ? $lastLog->login_at->format('c') : (string) $lastLog->login_at)
                : null;
            return (new UserResource($user))->toArray(request());
        });

        return response()->json(['user' => $data]);
    }

    /**
     * Secondary data for edit page (dropdowns, lists). Fetched in parallel with prime on frontend.
     */
    public function extras(User $user): JsonResponse
    {
        if ($user->hasRole('superadmin') && request()->user()->id !== $user->id) {
            return response()->json(['message' => 'Only the super admin can view this account.'], 403);
        }

        $ttl = (int) (config('cache.user_extras_ttl', 600)); // 10 min for reference data
        $cacheKey = 'user.extras.' . $user->id;

        $data = Cache::remember($cacheKey, $ttl, function () use ($user) {
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
            $roles = Role::orderBy('name')->get(['id', 'name', 'description']);
            return [
                'roles' => $roles,
                'managers' => $managers,
                'team_leaders' => $teamLeaders,
                'team_leader_role_id' => $teamLeaderRoleId,
                'sales_agent_role_id' => $salesAgentRoleId,
                'manager_label' => $mappings['manager']['label'] ?? 'Manager',
                'team_leader_label' => $mappings['team_leader']['label'] ?? 'Team Leader',
            ];
        });

        return response()->json($data);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->hasRole('superadmin') && $request->user()->id !== $user->id) {
            return response()->json(['message' => 'Only the super admin can edit their own account.'], 403);
        }

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
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:users,employee_number,' . $user->id],
            'department' => ['nullable', 'string', 'max:100'],
            'extension' => ['nullable', 'string', 'max:20'],
            'joining_date' => ['nullable', 'date'],
            'terminate_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:approved,rejected,pending'],
        ]);

        $user->fill(collect($validated)->except(['password', 'roles', 'manager_id', 'team_leader_id'])->toArray());

        if (array_key_exists('roles', $validated) && !empty($validated['roles'])) {
            $requestedStatus = $validated['status'] ?? null;
            if ($requestedStatus !== 'rejected') {
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
            }
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

    /**
     * Inline edit: update a single field. Strict: super admin only editable by self; others by super admin or users.edit.
     */
    public function patch(Request $request, User $user): JsonResponse
    {
        if ($user->hasRole('superadmin')) {
            if ($request->user()->id !== $user->id) {
                return response()->json(['message' => 'Only the super admin can edit their own account.'], 403);
            }
        } else {
            if (! $request->user()->hasRole('superadmin') && ! $request->user()->can('users.edit')) {
                return response()->json(['message' => 'You do not have permission to edit users.'], 403);
            }
        }

        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in([
                'name', 'email', 'phone', 'country', 'status', 'employee_number',
                'department', 'extension', 'joining_date', 'terminate_date',
            ])],
            'value' => ['nullable'],
        ]);

        $field = $validated['field'];
        $value = $validated['value'];

        if ($field === 'status') {
            $request->validate(['value' => ['required', 'string', Rule::in(['approved', 'rejected', 'pending'])]]);
            $user->status = $value;
            if ($value === 'approved') {
                $user->approved_by = auth()->id();
                $user->approved_at = now();
                $user->rejected_by = null;
                $user->rejected_at = null;
                $user->rejection_reason = null;
            } elseif ($value === 'rejected') {
                $user->rejected_by = auth()->id();
                $user->rejected_at = now();
            }
            $user->save();
        } elseif (in_array($field, ['joining_date', 'terminate_date'], true)) {
            $user->$field = $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : null;
            $user->save();
        } else {
            if ($field === 'email') {
                $request->validate(['value' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id]]);
            }
            if ($field === 'name') {
                $request->validate(['value' => ['required', 'string', 'max:255']]);
            }
            if (in_array($field, ['phone', 'country', 'employee_number', 'department', 'extension'], true)) {
                $user->$field = $value === '' || $value === null ? null : (string) $value;
            } else {
                $user->$field = $value;
            }
            $user->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->refresh();
        $out = ['id' => $user->id, $field => $user->$field];
        if (in_array($field, ['joining_date', 'terminate_date'], true) && $user->$field) {
            $out[$field] = $user->$field->format('Y-m-d');
        }

        return response()->json($out);
    }

    public function auditLog(User $user): JsonResponse
    {
        if ($user->hasRole('superadmin') && request()->user()->id !== $user->id) {
            return response()->json(['message' => 'Only the super admin can view this account.'], 403);
        }

        $logs = UserAudit::query()
            ->where('user_id', $user->id)
            ->with('changedByUser:id,name')
            ->orderByDesc('changed_at')
            ->limit(200)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'field_name' => $log->field_name,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'changed_at' => $log->changed_at?->toIso8601String(),
                'changed_by' => $log->changedByUser?->name,
            ]);

        return response()->json(['data' => $logs]);
    }

    /**
     * Send password reset email to the user. Super admin's password can only be reset by a super admin (self).
     */
    public function sendPasswordReset(User $user): JsonResponse
    {
        if ($user->hasRole('superadmin')) {
            if ($user->id !== request()->user()->id) {
                return response()->json(['message' => 'Only the super admin can reset their own password.'], 403);
            }
        } else {
            if (! request()->user()->hasRole('superadmin') && ! request()->user()->can('users.edit')) {
                return response()->json(['message' => 'You do not have permission to reset user passwords.'], 403);
            }
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => __('Password reset link sent. They will receive an email with instructions to set a new password.')]);
    }
}
