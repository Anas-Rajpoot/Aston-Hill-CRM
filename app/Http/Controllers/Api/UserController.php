<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendUserPasswordResetLink;
use App\Http\Resources\UserResource;
use App\Models\SystemAuditLog;
use App\Models\TeamRoleMapping;
use App\Models\User;
use App\Models\UserAudit;
use App\Models\UserColumnPreference;
use App\Models\UserLoginLog;
use App\Models\UserMonthlyTargetHistory;
use App\Notifications\UserApprovalStatusNotification;
use App\Rules\MeetsPasswordPolicy;
use App\Support\RbacPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;

    private const MODULE = 'users';

    private const ALLOWED_COLUMNS = [
        'id', 'name', 'email', 'phone', 'country', 'roles', 'status', 'last_login_at',
        'created_at', 'employee_number', 'department', 'extension', 'joining_date', 'terminate_date',
        'manager', 'team_leader', 'monthly_target',
    ];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(function (Request $request, $next) {
            [$action, $legacy] = $this->resolveActionForMethod((string) ($request->route()?->getActionMethod() ?? ''));
            $this->authorizeAction($request, $action, $legacy);

            return $next($request);
        });
    }

    private function cachedRolesList()
    {
        return Cache::remember('users.roles_list_v1', 600, function () {
            return Role::where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name', 'description']);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['manager_id', 'team_leader_id']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
            'name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'string', 'max:200'],
            'role' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string'], // comma-separated or single
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'joining_from' => ['sometimes', 'nullable', 'date'],
            'joining_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:joining_from'],
            'terminate_from' => ['sometimes', 'nullable', 'date'],
            'terminate_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:terminate_from'],
            'created_from' => ['sometimes', 'nullable', 'date'],
            'created_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:created_from'],
            'target_month_from' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'target_month_to' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'target_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
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

        $roles = $this->cachedRolesList();

        // Cache stats for 30s to avoid expensive scan on every list request
        $statsCacheKey = 'user_stats_' . ($requestUser->hasRole('superadmin') ? 'sa' : 'non_sa');
        $stats = Cache::remember($statsCacheKey, 30, function () use ($requestUser) {
            $statsQuery = User::query()
                ->when(! $requestUser->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')));
            $statsRow = $statsQuery
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active")
                ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as inactive")
                ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
                ->first();
            $totalTargetMrc = User::query()
                ->when(! $requestUser->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')))
                ->where('status', 'approved')
                ->sum('monthly_target');
            return [
                'total' => (int) ($statsRow->total ?? 0),
                'active' => (int) ($statsRow->active ?? 0),
                'inactive' => (int) ($statsRow->inactive ?? 0),
                'pending' => (int) ($statsRow->pending ?? 0),
                'total_target_mrc' => round((float) $totalTargetMrc, 2),
            ];
        });

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
                'monthly_target' => $u->monthly_target,
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
        if (! empty($validated['department'])) {
            $query->where('department', $validated['department']);
        }
        if (! empty($validated['manager_id'])) {
            $query->where('manager_id', $validated['manager_id']);
        }
        if (! empty($validated['team_leader_id'])) {
            $query->where('team_leader_id', $validated['team_leader_id']);
        }
        if (! empty($validated['joining_from'])) {
            $query->whereDate('joining_date', '>=', $validated['joining_from']);
        }
        if (! empty($validated['joining_to'])) {
            $query->whereDate('joining_date', '<=', $validated['joining_to']);
        }
        if (! empty($validated['terminate_from'])) {
            $query->whereDate('terminate_date', '>=', $validated['terminate_from']);
        }
        if (! empty($validated['terminate_to'])) {
            $query->whereDate('terminate_date', '<=', $validated['terminate_to']);
        }
        // Monthly target range filters (via history table)
        if (! empty($validated['target_month_from']) || ! empty($validated['target_month_to'])) {
            $query->whereHas('monthlyTargetHistory', function ($q) use ($validated) {
                if (! empty($validated['target_month_from'])) {
                    $q->where('month', '>=', $validated['target_month_from']);
                }
                if (! empty($validated['target_month_to'])) {
                    $q->where('month', '<=', $validated['target_month_to']);
                }
                if (! empty($validated['target_min'])) {
                    $q->where('target_amount', '>=', (float) $validated['target_min']);
                }
                if (! empty($validated['target_max'])) {
                    $q->where('target_amount', '<=', (float) $validated['target_max']);
                }
            });
        } elseif (! empty($validated['target_min']) || ! empty($validated['target_max'])) {
            if (! empty($validated['target_min'])) {
                $query->where('monthly_target', '>=', (float) $validated['target_min']);
            }
            if (! empty($validated['target_max'])) {
                $query->where('monthly_target', '<=', (float) $validated['target_max']);
            }
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
                // SQLite sorts NULLs first by default; force NULLs to the bottom
                // so "Last Login" sorting behaves as expected.
                ->orderByRaw('CASE WHEN last_logins.login_at IS NULL THEN 1 ELSE 0 END ASC')
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
        $roles = $this->cachedRolesList()
            ->map(fn ($r) => ['value' => $r->name, 'label' => $r->name])
            ->values()
            ->all();

        $departments = User::whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort()
            ->map(fn ($d) => ['value' => $d, 'label' => ucfirst($d)])
            ->values()
            ->all();

        $managers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['manager', 'superadmin']))
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])
            ->all();

        $teamLeaders = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['team_leader', 'manager', 'superadmin']))
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])
            ->all();

        return response()->json([
            'statuses' => [
                ['value' => 'approved', 'label' => 'Active'],
                ['value' => 'rejected', 'label' => 'Inactive'],
                ['value' => 'pending', 'label' => 'Pending Approval'],
            ],
            'roles' => $roles,
            'departments' => $departments,
            'managers' => $managers,
            'team_leaders' => $teamLeaders,
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
            'password' => ['required', 'string', 'confirmed', new MeetsPasswordPolicy],
            'phone' => ['nullable', 'string', 'regex:/^971\d{9}$/'],
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

        // Set must_change_password if security settings require it for first login
        $mustChange = false;
        try {
            $mustChange = \App\Models\SecuritySetting::current()->force_password_reset_on_first_login;
        } catch (\Throwable $e) {}

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'country' => $validated['country'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'employee_number' => $validated['employee_number'] ?? null,
            'department' => $validated['department'] ?? null,
            'extension' => $validated['extension'] ?? null,
            'joining_date' => $validated['joining_date'] ?? null,
            'terminate_date' => $validated['terminate_date'] ?? null,
            'must_change_password' => $mustChange,
            'password_changed_at' => now(),
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
        $ids = collect($validated['ids']);
        $superAdminIds = User::whereIn('id', $ids)->whereHas('roles', fn ($r) => $r->where('name', 'superadmin'))->pluck('id');
        $idsToActivate = $ids->diff($superAdminIds)->values()->all();
        $count = User::whereIn('id', $idsToActivate)->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
        try {
            SystemAuditLog::record('user.bulk_activated', null, ['user_ids' => $idsToActivate, 'count' => count($idsToActivate)], $request->user()->id, 'user');
        } catch (\Throwable $e) {}
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
        try {
            SystemAuditLog::record('user.bulk_deactivated', null, ['user_ids' => $idsToDeactivate, 'count' => count($idsToDeactivate)], $request->user()->id, 'user');
        } catch (\Throwable $e) {}
        // Roles are preserved on deactivation; only super admin / authorized user can change roles explicitly.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json(['message' => "{$count} user(s) deactivated.", 'count' => $count]);
    }

    public function bulkAssignMonthlyTarget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
            'monthly_target' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $ids = collect($validated['ids'])->unique()->values();
        $superAdminIds = User::whereIn('id', $ids)
            ->whereHas('roles', fn ($r) => $r->where('name', 'superadmin'))
            ->pluck('id');

        $idsToAssign = $ids->diff($superAdminIds)->values();
        if ($idsToAssign->isEmpty()) {
            return response()->json([
                'message' => 'No eligible users selected for target assignment.',
                'count' => 0,
            ]);
        }

        $target = (float) $validated['monthly_target'];
        $month = $validated['month'];
        $actorId = (int) $request->user()->id;
        $now = now();

        DB::transaction(function () use ($idsToAssign, $target, $month, $actorId, $now) {
            User::whereIn('id', $idsToAssign->all())->update([
                'monthly_target' => $target,
                'updated_at' => $now,
            ]);

            // Enforce: one target per user per month (no duplicates in history table).
            UserMonthlyTargetHistory::whereIn('user_id', $idsToAssign->all())
                ->where('month', $month)
                ->delete();

            $historyRows = $idsToAssign
                ->map(fn ($id) => [
                    'user_id' => (int) $id,
                    'month' => $month,
                    'target_amount' => $target,
                    'set_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            UserMonthlyTargetHistory::insert($historyRows);
        });

        try {
            SystemAuditLog::record(
                'user.bulk_monthly_target_assigned',
                null,
                ['user_ids' => $idsToAssign->all(), 'month' => $month, 'target' => $target, 'count' => $idsToAssign->count()],
                $actorId,
                'user'
            );
        } catch (\Throwable $e) {}

        Cache::forget('user_stats_sa');
        Cache::forget('user_stats_non_sa');

        return response()->json([
            'message' => $idsToAssign->count() . ' user(s) monthly target updated.',
            'count' => $idsToAssign->count(),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        if ($user->hasRole('superadmin') && request()->user()->id !== $user->id) {
            return response()->json(['message' => 'Only the super admin can view this account.'], 403);
        }

        $user->load('roles:id,name');
        $lastLog = UserLoginLog::where('user_id', $user->id)->orderByDesc('login_at')->first();
        $lastLoginAt = $lastLog?->login_at ? (is_object($lastLog->login_at) ? $lastLog->login_at->format('c') : (string) $lastLog->login_at) : null;
        $userData = $user->toArray();
        $userData['last_login_at'] = $lastLoginAt;
        $roles = $this->cachedRolesList();
        $mappings = TeamRoleMapping::allMappings();
        $managerRoleId = TeamRoleMapping::roleIdFor('manager');
        $teamLeaderRoleId = TeamRoleMapping::roleIdFor('team_leader');
        $salesAgentRoleId = TeamRoleMapping::roleIdFor('sales_agent');
        $managers = $managerRoleId
            ? User::query()
                ->where('status', 'approved')
                ->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($q) => $q->where('id', $managerRoleId))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : collect();
        $teamLeaders = $teamLeaderRoleId
            ? User::query()
                ->where('status', 'approved')
                ->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($q) => $q->where('id', $teamLeaderRoleId))
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'manager_id'])
            : collect();
        return response()->json([
            'user' => $userData,
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
            $managers = $managerRoleId
                ? User::query()
                    ->where('status', 'approved')
                    ->where('id', '!=', $user->id)
                    ->whereHas('roles', fn ($q) => $q->where('id', $managerRoleId))
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                : collect();
            $teamLeaders = $teamLeaderRoleId
                ? User::query()
                    ->where('status', 'approved')
                    ->where('id', '!=', $user->id)
                    ->whereHas('roles', fn ($q) => $q->where('id', $teamLeaderRoleId))
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'manager_id'])
                : collect();
            $roles = $this->cachedRolesList();
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
            'phone' => ['nullable', 'string', 'regex:/^971\d{9}$/'],
            'country' => ['nullable', 'string', 'max:100'],
            'cnic_number' => ['nullable', 'string', 'max:20'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string', 'confirmed', new MeetsPasswordPolicy],
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

        $statusChanged = false;
        if (array_key_exists('roles', $validated) && !empty($validated['roles'])) {
            $requestedStatus = $validated['status'] ?? null;
            if ($requestedStatus !== 'rejected') {
            $request->validate(
                ['roles' => ['required', 'array', 'min:1']],
                ['roles.required' => 'Please select at least one role.']
            );
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
            $user->password = $validated['password'];
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
            $managerProvided = array_key_exists('manager_id', $validated);
            $teamLeaderProvided = array_key_exists('team_leader_id', $validated);
            if ($hasSalesAgent) {
                // Keep existing hierarchy when fields are omitted from UI.
                if ($managerProvided && !empty($validated['manager_id'])) {
                    $user->manager_id = (int) $validated['manager_id'];
                } elseif ($managerProvided) {
                    $user->manager_id = null;
                }
                if ($teamLeaderProvided && !empty($validated['team_leader_id'])) {
                    $user->team_leader_id = (int) $validated['team_leader_id'];
                } elseif ($teamLeaderProvided) {
                    $user->team_leader_id = null;
                }
            } elseif ($hasTeamLeader) {
                if ($managerProvided && !empty($validated['manager_id'])) {
                    $user->manager_id = (int) $validated['manager_id'];
                } elseif ($managerProvided) {
                    $user->manager_id = null;
                }
                // Team leaders should not have a team leader parent.
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
        $oldData = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
        try {
            SystemAuditLog::record('user.deleted', $oldData, null, request()->user()->id, 'user', $user->id);
        } catch (\Throwable $e) {}
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
                'department', 'extension', 'joining_date', 'terminate_date', 'monthly_target',
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
                if ($field === 'phone' && $value !== '' && $value !== null) {
                    $request->validate(['value' => ['nullable', 'string', 'regex:/^971\d{9}$/']]);
                }
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
                'changed_by' => $log->changed_by,
                'changed_by_name' => $log->changedByUser?->name ?? '—',
            ]);

        $logs = $this->resolveAuditDisplayValues($logs);

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

        // Keep API response fast; send email in background.
        if (config('queue.default') === 'sync') {
            // Even on sync queue, defer work until after response.
            SendUserPasswordResetLink::dispatchAfterResponse($user->email);
        } else {
            SendUserPasswordResetLink::dispatch($user->email)->onQueue('emails');
        }

        return response()->json(['message' => __('Password reset request queued. The user will receive an email with instructions shortly.')]);
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function resolveActionForMethod(string $method): array
    {
        return match ($method) {
            'store', 'bulkImport' => ['create', ['users.create', 'users.add']],
            'update', 'patch', 'bulkActivate', 'bulkDeactivate', 'sendPasswordReset', 'updateMonthlyTarget', 'bulkAssignMonthlyTarget' => ['update', ['users.edit', 'users.update']],
            'destroy', 'otpDelete' => ['delete', ['users.delete']],
            default => ['read', ['users.list', 'users.view']],
        };
    }

    /**
     * @param  array<int,string>  $legacy
     */
    private function authorizeAction(Request $request, string $action, array $legacy = []): void
    {
        $user = $request->user();
        if (! $user || ! RbacPermission::can($user, 'users', $action, $legacy)) {
            abort(403, 'Unauthorized');
        }
    }

    // ─────────────────────────────────────────────
    //  BULK EXPORT — Streamed CSV
    // ─────────────────────────────────────────────
    public function export(Request $request): StreamedResponse
    {
        $requestUser = $request->user();
        $query = User::query()
            ->with(['roles:id,name', 'manager:id,name', 'teamLeader:id,name'])
            ->when(! $requestUser->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')));

        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string'],
            'role'   => ['sometimes', 'nullable', 'string'],
        ]);
        $this->applyFilters($query, $validated);
        $query->orderBy('name');

        $headers = [
            'Name', 'Email', 'Phone', 'Country', 'Department', 'Employee Number',
            'Extension', 'Status', 'Roles', 'Joining Date', 'Terminate Date',
            'Monthly Target', 'Manager', 'Team Leader', 'Created At',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            $query->chunk(500, function ($users) use ($out) {
                foreach ($users as $u) {
                    fputcsv($out, [
                        $u->name,
                        $u->email,
                        $u->phone,
                        $u->country,
                        $u->department,
                        $u->employee_number,
                        $u->extension,
                        $u->status,
                        $u->roles->pluck('name')->implode(', '),
                        $u->joining_date?->format('Y-m-d'),
                        $u->terminate_date?->format('Y-m-d'),
                        $u->monthly_target,
                        $u->manager?->name,
                        $u->teamLeader?->name,
                        $u->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($out);
        }, 'users_export_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ─────────────────────────────────────────────
    //  IMPORT TEMPLATE — CSV with headers + 2 sample rows
    // ─────────────────────────────────────────────
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'name', 'email', 'phone', 'country', 'department', 'employee_number',
            'extension', 'status', 'roles', 'joining_date', 'terminate_date',
            'monthly_target', 'password',
        ];

        $samples = [
            ['John Doe', 'john.doe@company.com', '971501234567', 'UAE', 'sales', 'EMP001', '1001', 'approved', 'sales_agent', '2026-01-15', '', '5000.00', 'Password@1'],
            ['Jane Smith', 'jane.smith@company.com', '971509876543', 'UAE', 'backoffice', 'EMP002', '1002', 'approved', 'csr', '2026-02-01', '', '3000.00', 'Password@1'],
        ];

        return response()->streamDownload(function () use ($headers, $samples) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($samples as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'users_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ─────────────────────────────────────────────
    //  BULK IMPORT — CSV upload with validation
    // ─────────────────────────────────────────────
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $headerRow = fgetcsv($handle);

        if (! $headerRow) {
            fclose($handle);
            return response()->json(['message' => 'CSV file is empty or unreadable.'], 422);
        }

        $headerRow = array_map(fn ($h) => strtolower(trim($h)), $headerRow);
        $requiredHeaders = ['name', 'email', 'password'];
        $missing = array_diff($requiredHeaders, $headerRow);
        if (! empty($missing)) {
            fclose($handle);
            return response()->json([
                'message' => 'Missing required columns: ' . implode(', ', $missing),
            ], 422);
        }

        $imported = 0;
        $errors = [];
        $rowNum = 1;
        $existingEmails = User::pluck('email')->map(fn ($e) => strtolower($e))->toArray();
        $allRoles = Role::where('guard_name', 'web')->pluck('id', 'name');

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) !== count($headerRow)) {
                $errors[] = ['row' => $rowNum, 'error' => 'Column count mismatch.'];
                continue;
            }

            $data = array_combine($headerRow, $row);

            // Validate each row
            $v = Validator::make($data, [
                'name'            => ['required', 'string', 'max:255'],
                'email'           => ['required', 'email', 'max:255'],
                'password'        => ['required', 'string', 'min:8'],
                'phone'           => ['nullable', 'string'],
                'country'         => ['nullable', 'string', 'max:100'],
                'department'      => ['nullable', 'string', 'max:100'],
                'employee_number' => ['nullable', 'string', 'max:50'],
                'extension'       => ['nullable', 'string', 'max:20'],
                'status'          => ['nullable', 'string', 'in:pending,approved,rejected'],
                'joining_date'    => ['nullable', 'date'],
                'terminate_date'  => ['nullable', 'date'],
                'monthly_target'  => ['nullable', 'numeric', 'min:0'],
            ]);

            if ($v->fails()) {
                $errors[] = ['row' => $rowNum, 'error' => $v->errors()->first()];
                continue;
            }

            $email = strtolower(trim($data['email']));
            if (in_array($email, $existingEmails, true)) {
                $errors[] = ['row' => $rowNum, 'error' => "Email {$email} already exists."];
                continue;
            }

            try {
                $user = User::create([
                    'name'            => trim($data['name']),
                    'email'           => $email,
                    'password'        => $data['password'],
                    'phone'           => ! empty($data['phone']) ? trim($data['phone']) : null,
                    'country'         => ! empty($data['country']) ? trim($data['country']) : null,
                    'department'      => ! empty($data['department']) ? trim($data['department']) : null,
                    'employee_number' => ! empty($data['employee_number']) ? trim($data['employee_number']) : null,
                    'extension'       => ! empty($data['extension']) ? trim($data['extension']) : null,
                    'status'          => ! empty($data['status']) ? trim($data['status']) : 'pending',
                    'joining_date'    => ! empty($data['joining_date']) ? $data['joining_date'] : null,
                    'terminate_date'  => ! empty($data['terminate_date']) ? $data['terminate_date'] : null,
                    'monthly_target'  => ! empty($data['monthly_target']) ? (float) $data['monthly_target'] : null,
                    'must_change_password' => true,
                    'password_changed_at'  => now(),
                ]);

                // Assign roles from CSV (comma-separated role names)
                if (! empty($data['roles'])) {
                    $roleNames = array_map('trim', explode(',', $data['roles']));
                    $roleIds = [];
                    foreach ($roleNames as $rn) {
                        $rn = strtolower($rn);
                        if ($rn === 'superadmin') continue;
                        if ($allRoles->has($rn)) {
                            $roleIds[] = $allRoles[$rn];
                        }
                    }
                    if (! empty($roleIds)) {
                        $user->syncRoles(Role::whereIn('id', $roleIds)->get());
                    }
                }

                if ($user->status === 'approved') {
                    $user->update([
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                }

                $existingEmails[] = $email;
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'error' => 'Database error: ' . $e->getMessage()];
            }
        }

        fclose($handle);

        try {
            SystemAuditLog::record('user.bulk_imported', null, ['imported' => $imported, 'errors' => count($errors)], $request->user()->id, 'user');
        } catch (\Throwable $e) {}

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message'  => "{$imported} user(s) imported successfully.",
            'imported' => $imported,
            'errors'   => $errors,
        ]);
    }

    // ─────────────────────────────────────────────
    //  MONTHLY TARGET — Update + History
    // ─────────────────────────────────────────────
    public function updateMonthlyTarget(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'monthly_target' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'month'          => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $month = $validated['month'];
        $target = (float) $validated['monthly_target'];

        DB::transaction(function () use ($user, $month, $target) {
            // Save current target on user row.
            $user->monthly_target = $target;
            $user->save();

            // Enforce: one target per user per month (replace history row).
            UserMonthlyTargetHistory::where('user_id', $user->id)
                ->where('month', $month)
                ->delete();

            UserMonthlyTargetHistory::create([
                'user_id' => $user->id,
                'month' => $month,
                'target_amount' => $target,
                'set_by' => auth()->id(),
            ]);
        });

        Cache::forget('user_stats_sa');
        Cache::forget('user_stats_non_sa');

        return response()->json([
            'message'        => 'Monthly target updated.',
            'monthly_target' => $user->monthly_target,
        ]);
    }

    public function monthlyTargetHistory(User $user): JsonResponse
    {
        // Return only latest row per month to avoid showing duplicates
        // (rule: one target allocation per month).
        $latestIds = DB::table('user_monthly_target_history')
            ->select(DB::raw('MAX(id) as id'))
            ->where('user_id', $user->id)
            ->groupBy('month');

        $history = UserMonthlyTargetHistory::query()
            ->select('user_monthly_target_history.*')
            ->joinSub($latestIds, 'latest', fn ($join) => $join->on('user_monthly_target_history.id', '=', 'latest.id'))
            ->with('setByUser:id,name')
            ->where('user_monthly_target_history.user_id', $user->id)
            ->orderByDesc('user_monthly_target_history.created_at')
            ->orderByDesc('user_monthly_target_history.id')
            ->limit(50)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'month' => $h->month,
                'target_amount' => $h->target_amount,
                'set_by_name' => $h->setByUser?->name ?? '—',
                'created_at' => $h->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $history]);
    }

    // ─────────────────────────────────────────────
    //  OTP-VERIFIED DELETE
    // ─────────────────────────────────────────────
    public function otpDelete(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $authUser = $request->user();

        // Verify OTP — check against the session-stored OTP
        $storedOtp = session('delete_otp_' . $user->id);
        $storedExpiry = session('delete_otp_expiry_' . $user->id);

        if (! $storedOtp || $storedOtp !== $validated['otp'] || now()->timestamp > ($storedExpiry ?? 0)) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        // Clear OTP from session
        session()->forget(['delete_otp_' . $user->id, 'delete_otp_expiry_' . $user->id]);

        $oldData = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
        try {
            SystemAuditLog::record('user.deleted', $oldData, null, $authUser->id, 'user', $user->id);
        } catch (\Throwable $e) {}

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    public function requestDeleteOtp(Request $request, User $user): JsonResponse
    {
        if ($user->hasRole('superadmin') && $request->user()->id !== $user->id) {
            return response()->json(['message' => 'Cannot delete super admin.'], 403);
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store in session with 5 min expiry
        session([
            'delete_otp_' . $user->id        => $otp,
            'delete_otp_expiry_' . $user->id  => now()->addMinutes(5)->timestamp,
        ]);

        // In production, send OTP via email/SMS. For now, return in response for admin.
        return response()->json([
            'message' => 'OTP generated. Valid for 5 minutes.',
            'otp'     => $otp, // Remove in production — send via notification instead
        ]);
    }
}
