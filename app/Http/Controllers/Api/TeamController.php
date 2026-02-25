<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\SystemAuditLog;
use App\Models\Team;
use App\Models\User;
use App\Models\UserColumnPreference;
use App\Support\RbacPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\TeamHierarchyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    private const MODULE = 'teams';

    private const ALLOWED_COLUMNS = [
        'id', 'name', 'description', 'manager', 'team_leader',
        'department', 'status', 'max_members', 'members_count',
        'created_at',
    ];

    private const DEFAULT_COLUMNS = [
        'id', 'name', 'manager', 'team_leader', 'department',
        'status', 'members_count', 'created_at',
    ];

    /* ───── Index ───── */

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['teams.list', 'teams.view']);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(self::ALLOWED_COLUMNS)],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Team::STATUSES)],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'name';
        $order = strtolower($validated['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Team::query()
            ->withCount('members')
            ->with(['manager:id,name', 'teamLeader:id,name']);

        $this->applyFilters($query, $validated);
        $total = $query->count();
        $this->applySort($query, $sort, $order);

        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->map(function ($team) use ($columns) {
            return $this->formatRow($team, $columns);
        });

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $stats = [
            'total' => Team::count(),
            'active' => Team::where('status', 'active')->count(),
            'inactive' => Team::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'stats' => $stats,
        ]);
    }

    /* ───── Store ───── */

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$managerIds, $leaderIds, $memberIds] = $this->resolveRoleAssignments($data);
        $data['manager_id'] = $managerIds[0] ?? null;
        $data['team_leader_id'] = $leaderIds[0] ?? null;
        unset($data['manager_ids'], $data['team_leader_ids'], $data['member_ids']);

        $team = Team::create($data);
        $this->syncTeamAssignments($team, $managerIds, $leaderIds, $memberIds);
        $team->load(['manager:id,name', 'teamLeader:id,name']);

        try {
            SystemAuditLog::record('team.created', null, [
                'team_id' => $team->id,
                'name' => $team->name,
            ], $request->user()->id, 'team');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Team created successfully.',
            'team' => $team,
        ], 201);
    }

    /* ───── Show ───── */

    public function show(Team $team): JsonResponse
    {
        $this->authorizeAction(request(), 'read', ['teams.view', 'teams.list']);

        $team->load(['manager:id,name,email', 'teamLeader:id,name,email']);
        $team->loadCount('members');

        $members = User::where('team_id', $team->id)
            ->select(['id', 'name', 'email', 'status', 'department', 'team_id'])
            ->with('roles:id,name')
            ->orderBy('name')
            ->get();

        $managerIds = $members->filter(fn ($u) => $u->roles->pluck('name')->contains('manager'))->pluck('id');
        $leaderIds = $members->filter(fn ($u) => $u->roles->pluck('name')->contains('team_leader'))->pluck('id');
        $memberIds = $members->pluck('id')->diff($managerIds)->diff($leaderIds)->values();

        if ($team->manager_id) $managerIds->prepend((int) $team->manager_id);
        if ($team->team_leader_id) $leaderIds->prepend((int) $team->team_leader_id);

        $teamData = $team->toArray();
        $teamData['manager_ids'] = $managerIds->map(fn ($id) => (int) $id)->unique()->values()->all();
        $teamData['team_leader_ids'] = $leaderIds->map(fn ($id) => (int) $id)->unique()->values()->all();
        $teamData['member_ids'] = $memberIds->map(fn ($id) => (int) $id)->unique()->values()->all();

        return response()->json([
            'team' => $teamData,
            'members' => $members,
        ]);
    }

    /* ───── Update ───── */

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $oldData = $team->toArray();
        $data = $request->validated();
        [$managerIds, $leaderIds, $memberIds] = $this->resolveRoleAssignments($data);
        $data['manager_id'] = $managerIds[0] ?? null;
        $data['team_leader_id'] = $leaderIds[0] ?? null;
        unset($data['manager_ids'], $data['team_leader_ids'], $data['member_ids']);

        $team->update($data);
        $this->syncTeamAssignments($team, $managerIds, $leaderIds, $memberIds);
        $team->load(['manager:id,name', 'teamLeader:id,name']);
        $team->loadCount('members');

        try {
            SystemAuditLog::record('team.updated', $oldData, $team->toArray(), $request->user()->id, 'team', $team->id);
        } catch (\Throwable $e) {
            report($e);
        }

        TeamHierarchyService::flushAllCaches();

        return response()->json([
            'message' => 'Team updated successfully.',
            'team' => $team,
        ]);
    }

    /* ───── Destroy ───── */

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->authorizeAction($request, 'delete', ['teams.delete']);
        $user = $request->user();

        $memberCount = $team->members()->count();
        if ($memberCount > 0) {
            return response()->json([
                'message' => "Cannot delete team '{$team->name}': it still has {$memberCount} member(s). Remove all members first.",
            ], 422);
        }

        $oldData = ['id' => $team->id, 'name' => $team->name];
        try {
            SystemAuditLog::record('team.deleted', $oldData, null, $user->id, 'team', $team->id);
        } catch (\Throwable $e) {
            report($e);
        }

        $team->delete();

        return response()->json(['message' => 'Team deleted successfully.']);
    }

    /* ───── Bulk Actions ───── */

    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'delete', ['teams.delete']);
        $user = $request->user();

        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'exists:teams,id']]);

        $teamsWithMembers = Team::whereIn('id', $data['ids'])->withCount('members')
            ->get()->filter(fn ($t) => $t->members_count > 0);

        if ($teamsWithMembers->isNotEmpty()) {
            $names = $teamsWithMembers->pluck('name')->implode(', ');
            return response()->json([
                'message' => "Cannot delete teams that still have members: {$names}",
            ], 422);
        }

        $count = Team::whereIn('id', $data['ids'])->delete();

        try {
            SystemAuditLog::record('team.bulk_deleted', null, ['ids' => $data['ids'], 'count' => $count], $user->id, 'team');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => "{$count} team(s) deleted.", 'count' => $count]);
    }

    public function bulkStatusChange(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'update', ['teams.edit']);
        $user = $request->user();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:teams,id'],
            'status' => ['required', Rule::in(Team::STATUSES)],
        ]);

        $count = Team::whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        try {
            SystemAuditLog::record('team.bulk_status_changed', null, [
                'ids' => $data['ids'],
                'status' => $data['status'],
                'count' => $count,
            ], $user->id, 'team');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => "{$count} team(s) updated to {$data['status']}.", 'count' => $count]);
    }

    /* ───── Members ───── */

    public function members(Team $team): JsonResponse
    {
        $this->authorizeAction(request(), 'read', ['teams.view', 'teams.list']);

        $members = User::where('team_id', $team->id)
            ->select(['id', 'name', 'email', 'status', 'department'])
            ->with('roles:id,name')
            ->orderBy('name')
            ->get();

        return response()->json(['members' => $members]);
    }

    public function addMembers(Request $request, Team $team): JsonResponse
    {
        $this->authorizeAction($request, 'update', ['teams.manage_members', 'teams.edit']);
        $user = $request->user();

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        if ($team->max_members) {
            $currentCount = $team->members()->count();
            $newCount = count($data['user_ids']);
            if (($currentCount + $newCount) > $team->max_members) {
                return response()->json([
                    'message' => "Cannot add {$newCount} member(s). Team limit is {$team->max_members} and currently has {$currentCount} member(s).",
                ], 422);
            }
        }

        $updated = User::whereIn('id', $data['user_ids'])->update(['team_id' => $team->id]);

        try {
            SystemAuditLog::record('team.members_added', null, [
                'team_id' => $team->id,
                'user_ids' => $data['user_ids'],
            ], $user->id, 'team', $team->id);
        } catch (\Throwable $e) {
            report($e);
        }

        TeamHierarchyService::flushAllCaches();

        return response()->json([
            'message' => "{$updated} member(s) added to team.",
            'updated_count' => $updated,
        ]);
    }

    public function removeMember(Request $request, Team $team, User $member): JsonResponse
    {
        $this->authorizeAction($request, 'update', ['teams.manage_members', 'teams.edit']);
        $user = $request->user();

        if ((int) $member->team_id !== (int) $team->id) {
            return response()->json(['message' => 'User is not a member of this team.'], 422);
        }

        $member->update(['team_id' => null]);

        try {
            SystemAuditLog::record('team.member_removed', null, [
                'team_id' => $team->id,
                'user_id' => $member->id,
            ], $user->id, 'team', $team->id);
        } catch (\Throwable $e) {
            report($e);
        }

        TeamHierarchyService::flushAllCaches();

        return response()->json(['message' => 'Member removed from team.']);
    }

    /* ───── Filters & Columns ───── */

    public function filters(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['teams.list', 'teams.view']);

        $departments = Team::whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort()
            ->values()
            ->map(fn ($d) => ['value' => $d, 'label' => $d]);

        $managers = User::whereIn('id', Team::whereNotNull('manager_id')->pluck('manager_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $teamLeaders = User::whereIn('id', Team::whereNotNull('team_leader_id')->pluck('team_leader_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
            'departments' => $departments,
            'managers' => $managers,
            'team_leaders' => $teamLeaders,
        ]);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['teams.list', 'teams.view']);

        $allColumns = collect(self::ALLOWED_COLUMNS)->map(fn ($key) => [
            'key' => $key,
            'label' => $this->columnLabel($key),
        ])->values()->all();

        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', self::MODULE)
            ->first();

        $visible = $pref?->visible_columns ?? self::DEFAULT_COLUMNS;

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['teams.list', 'teams.view']);

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

    /** Available users to add as team members (not already in any team). */
    public function availableMembers(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['teams.list', 'teams.view']);

        $teamId = $request->query('team_id');

        $users = User::where('status', 'approved')
            ->where(function ($q) use ($teamId) {
                $q->whereNull('team_id');
                if ($teamId) {
                    $q->orWhere('team_id', $teamId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department', 'team_id']);

        return response()->json(['users' => $users]);
    }

    /* ───── Private helpers ───── */

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

        $cols = $preference?->visible_columns ?? self::DEFAULT_COLUMNS;
        $cols = is_array($cols) ? $cols : [];

        return array_values(array_intersect($cols, self::ALLOWED_COLUMNS));
    }

    private function applyFilters($query, array $validated): void
    {
        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('department', 'like', $term);
            });
        }
        if (! empty($validated['name'])) {
            $term = '%' . addcslashes($validated['name'], '%_\\') . '%';
            $query->where('name', 'like', $term);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
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
    }

    private function applySort($query, string $sort, string $order): void
    {
        $direction = $order === 'asc' ? 'asc' : 'desc';

        if ($sort === 'manager') {
            $query->leftJoin('users as manager_u', 'teams.manager_id', '=', 'manager_u.id')
                ->orderBy('manager_u.name', $direction)
                ->select('teams.*');
            return;
        }
        if ($sort === 'team_leader') {
            $query->leftJoin('users as tl_u', 'teams.team_leader_id', '=', 'tl_u.id')
                ->orderBy('tl_u.name', $direction)
                ->select('teams.*');
            return;
        }
        if ($sort === 'members_count') {
            $query->orderBy('members_count', $direction);
            return;
        }

        $query->orderBy('teams.' . $sort, $direction);
    }

    private function formatRow(Team $team, array $columns): array
    {
        $row = ['id' => $team->id];

        foreach ($columns as $col) {
            if ($col === 'id') continue;
            if ($col === 'manager') {
                $row['manager'] = $team->manager?->name ?? null;
                $row['manager_id'] = $team->manager_id;
                continue;
            }
            if ($col === 'team_leader') {
                $row['team_leader'] = $team->teamLeader?->name ?? null;
                $row['team_leader_id'] = $team->team_leader_id;
                continue;
            }
            if ($col === 'members_count') {
                $row['members_count'] = $team->members_count ?? 0;
                continue;
            }
            if ($col === 'created_at') {
                $row['created_at'] = $team->created_at?->toIso8601String();
                continue;
            }
            $row[$col] = $team->$col ?? null;
        }

        return $row;
    }

    private function columnLabel(string $key): string
    {
        $labels = [
            'id' => 'ID',
            'name' => 'Team Name',
            'description' => 'Description',
            'manager' => 'Manager',
            'team_leader' => 'Team Leader',
            'department' => 'Department',
            'status' => 'Status',
            'max_members' => 'Max Members',
            'members_count' => 'Members',
            'created_at' => 'Created',
        ];

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    private function resolveRoleAssignments(array $data): array
    {
        $managerIds = collect($data['manager_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $leaderIds = collect($data['team_leader_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $memberIds = collect($data['member_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        if ($managerIds->isEmpty() && ! empty($data['manager_id'])) {
            $managerIds = collect([(int) $data['manager_id']]);
        }
        if ($leaderIds->isEmpty() && ! empty($data['team_leader_id'])) {
            $leaderIds = collect([(int) $data['team_leader_id']]);
        }

        // Keep slots mutually exclusive.
        $leaderIds = $leaderIds->diff($managerIds)->values();
        $memberIds = $memberIds->diff($managerIds)->diff($leaderIds)->values();

        return [
            $managerIds->unique()->values()->all(),
            $leaderIds->unique()->values()->all(),
            $memberIds->unique()->values()->all(),
        ];
    }

    private function syncTeamAssignments(Team $team, array $managerIds, array $leaderIds, array $memberIds): void
    {
        $selectedIds = collect(array_merge($managerIds, $leaderIds, $memberIds))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Remove users no longer selected for this team.
        User::where('team_id', $team->id)
            ->when(! empty($selectedIds), fn ($q) => $q->whereNotIn('id', $selectedIds))
            ->update(['team_id' => null]);

        if (! empty($selectedIds)) {
            User::whereIn('id', $selectedIds)->update(['team_id' => $team->id]);
        }
    }

    private function authorizeAction(Request $request, string $action, array $legacy = []): void
    {
        $user = $request->user();
        if (! $user || ! RbacPermission::can($user, 'teams', $action, $legacy)) {
            abort(403, 'Unauthorized.');
        }
    }
}
