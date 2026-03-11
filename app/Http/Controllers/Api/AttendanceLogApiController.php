<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Support\RbacPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AttendanceLogApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * List attendance (login) logs with filters. Paginated for table.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:login_at,employee_name,employee_id,role,department,login_date,login_time,status'],
            'order' => ['sometimes', 'string', 'in:asc,desc'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'role' => ['sometimes', 'nullable', 'string', 'max:50'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'status' => ['sometimes', 'nullable', 'string', 'in:logged_in,logged_out,missing_logout'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'login_at';
        $order = $validated['order'] ?? 'desc';

        $query = UserLoginLog::query()
            ->with(['user:id,name,email,employee_number,department'])
            ->select('user_login_logs.*')
            ->leftJoin('users', 'user_login_logs.user_id', '=', 'users.id');

        if ($sort === 'employee_name') {
            $query->orderBy('users.name', $order);
        } elseif ($sort === 'department') {
            $query->orderBy('users.department', $order === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('user_login_logs.login_at', $order);
        }

        if (! empty($validated['user_id'])) {
            $query->where('user_login_logs.user_id', $validated['user_id']);
        }

        if (! empty($validated['role'])) {
            $role = $validated['role'];
            $query->whereHas('user.roles', fn ($q) => $q->where('name', $role));
        }

        if (! empty($validated['from'])) {
            $query->where('user_login_logs.login_at', '>=', $validated['from'] . ' 00:00:00');
        }

        if (! empty($validated['to'])) {
            $query->where('user_login_logs.login_at', '<=', $validated['to'] . ' 23:59:59');
        }

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'logged_out') {
                $query->whereNotNull('user_login_logs.logout_at');
            } elseif ($validated['status'] === 'logged_in') {
                $query->whereNull('user_login_logs.logout_at')
                    ->where('user_login_logs.login_at', '>=', now()->startOfDay());
            } elseif ($validated['status'] === 'missing_logout') {
                $query->whereNull('user_login_logs.logout_at')
                    ->where('user_login_logs.login_at', '<', now()->startOfDay());
            }
        }

        // Cache count for 30s to avoid expensive COUNT(*) on every paginated request
        $countCacheKey = 'attendance_count_' . md5(json_encode($validated));
        $total = Cache::remember($countCacheKey, 30, function () use ($query) {
            return (clone $query)->count();
        });
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $sessionIds = DB::table('sessions')->whereIn('id', $items->pluck('session_id')->filter()->values()->all())->pluck('id')->flip();
        $dayAggregates = $this->computeDayAggregates($items);

        $rows = $items->map(function (UserLoginLog $log) use ($sessionIds, $dayAggregates) {
            return $this->formatRow($log, $sessionIds, $dayAggregates);
        });

        // Fallback: if login logs are empty but there are active sessions, still show logged-in users.
        if ($total === 0) {
            $sessionFallback = $this->buildSessionFallbackRows($validated, $sort, $order, $page, $perPage);
            if (($sessionFallback['total'] ?? 0) > 0) {
                return response()->json([
                    'data' => $sessionFallback['data'],
                    'meta' => [
                        'current_page' => $page,
                        'last_page' => $sessionFallback['last_page'],
                        'per_page' => $perPage,
                        'total' => $sessionFallback['total'],
                    ],
                ]);
            }
        }

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Summary stats for dashboard cards: total_users, logged_in, logged_out, missing_logout.
     * Uses single query for login log counts to reduce round-trips.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $todayStart = now()->startOfDay();

        $totalUsers = User::query()->where('status', 'approved')->count();

        $logRow = UserLoginLog::query()->selectRaw(
            'SUM(CASE WHEN logout_at IS NULL AND login_at >= ? THEN 1 ELSE 0 END) as logged_in, SUM(CASE WHEN logout_at IS NOT NULL AND login_at >= ? THEN 1 ELSE 0 END) as logged_out, SUM(CASE WHEN logout_at IS NULL AND login_at < ? THEN 1 ELSE 0 END) as missing_logout',
            [$todayStart, $todayStart, $todayStart]
        )->first();

        $loggedInFromSessions = (int) DB::table('sessions')
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $loggedIn = max((int) ($logRow->logged_in ?? 0), $loggedInFromSessions);

        return response()->json([
            'total_users' => $totalUsers,
            'logged_in' => $loggedIn,
            'logged_out' => (int) ($logRow->logged_out ?? 0),
            'missing_logout' => (int) ($logRow->missing_logout ?? 0),
        ]);
    }

    /**
     * Filters for dropdowns: users, roles. Cached 10 min.
     */
    public function filters(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $data = Cache::remember('attendance_log_filters', 600, function () {
            $users = User::query()
                ->where('status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_number'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employee_id' => $u->employee_number ? 'EMP-' . str_pad((string) ($u->employee_number ?: $u->id), 3, '0', STR_PAD_LEFT) : 'EMP-' . str_pad((string) $u->id, 3, '0', STR_PAD_LEFT),
                ]);

            $roles = DB::table('roles')->orderBy('name')->pluck('name')->map(fn ($name) => [
                'value' => $name,
                'label' => str_replace('_', ' ', ucwords($name, '_')),
            ])->values()->all();

            return ['users' => $users, 'roles' => $roles];
        });

        return response()->json($data);
    }

    /**
     * Force logout a single log (session).
     */
    public function forceLogoutLog(Request $request, UserLoginLog $userLoginLog): JsonResponse
    {
        $this->authorizeForceLogout($request);

        if ($userLoginLog->session_id) {
            DB::table('sessions')->where('id', $userLoginLog->session_id)->delete();
        }

        if (! $userLoginLog->logout_at) {
            $userLoginLog->update(['logout_at' => now()]);
        }

        try {
            SystemAuditLog::record(
                'attendance.force_logout_log',
                null,
                ['log_id' => $userLoginLog->id, 'user_id' => $userLoginLog->user_id],
                $request->user()->id,
                'attendance',
                $userLoginLog->id
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json(['message' => 'Session logged out.']);
    }

    /**
     * Force logout user from all sessions.
     */
    public function forceLogoutUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeForceLogout($request);

        DB::table('sessions')->where('user_id', $user->id)->delete();

        UserLoginLog::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->update(['logout_at' => now()]);

        try {
            SystemAuditLog::record(
                'attendance.force_logout_user',
                null,
                ['user_id' => $user->id],
                $request->user()->id,
                'attendance',
                $user->id
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json(['message' => 'User logged out from all sessions.']);
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! RbacPermission::can($user, 'attendance', 'read', ['view_attendance_logs'])) {
            abort(403, 'Unauthorized.');
        }
    }

    private function authorizeForceLogout(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! RbacPermission::can($user, 'attendance', 'update', ['force_logout'])) {
            abort(403, 'Unauthorized.');
        }
    }

    private function formatRow(UserLoginLog $log, $sessionIds, array $dayAggregates = []): array
    {
        $user = $log->user;
        $loginAt = $log->login_at;
        $logoutAt = $log->logout_at;

        $employeeId = '—';
        if ($user) {
            $empNum = $user->employee_number ?: $user->id;
            $employeeId = 'EMP-' . str_pad((string) $empNum, 3, '0', STR_PAD_LEFT);
        }

        $roleLabel = $log->role
            ? str_replace('_', ' ', ucwords($log->role, '_'))
            : ($user ? $user->getRoleNames()->map(fn ($n) => str_replace('_', ' ', ucwords((string) $n, '_')))->implode(', ') : '—');
        $roleLabel = is_string($roleLabel) ? $roleLabel : '—';

        $department = $user->department ?? '—';

        $loginDate = $loginAt ? $loginAt->format('d M Y') : '—';
        $loginTime = $loginAt ? $loginAt->format('H:i:s') : '—';

        $hasLogout = $logoutAt !== null;
        $logoutTime = $hasLogout ? $logoutAt->format('H:i:s') : null;
        $sessionStillExists = $log->session_id && $sessionIds->has($log->session_id);

        if ($hasLogout) {
            $status = 'logged_out';
            $durationText = $this->durationBetween($loginAt, $logoutAt);
            $durationState = 'normal';
        } else {
            if ($sessionStillExists) {
                $status = 'logged_in';
                $durationText = 'In Progress';
                $durationState = 'in_progress';
            } else {
                $status = 'missing_logout';
                $durationText = 'Missing Logout';
                $durationState = 'missing';
            }
        }

        // Day-level aggregates: first login & last logout for this user on this date
        $dateKey = $log->user_id . '_' . ($loginAt ? $loginAt->toDateString() : '');
        $agg = $dayAggregates[$dateKey] ?? null;
        $firstLoginTime = $agg && $agg->first_login ? \Carbon\Carbon::parse($agg->first_login)->format('H:i:s') : '—';
        $lastLogoutTime = $agg && $agg->last_logout ? \Carbon\Carbon::parse($agg->last_logout)->format('H:i:s') : null;

        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'employee_name' => $user->name ?? '—',
            'employee_id' => $employeeId,
            'role' => $roleLabel,
            'department' => $department,
            'login_date' => $loginDate,
            'login_time' => $loginTime,
            'logout_time' => $logoutTime,
            'first_login_time' => $firstLoginTime,
            'last_logout_time' => $lastLogoutTime,
            'duration_text' => $durationText,
            'duration_state' => $durationState ?? 'normal',
            'status' => $status,
        ];
    }

    /**
     * Compute first login and last logout per user per day for items on the current page.
     * Returns associative array keyed by "{user_id}_{date}".
     */
    private function computeDayAggregates($items): array
    {
        $userIds = $items->pluck('user_id')->unique()->values()->all();
        $dates = $items->map(fn ($l) => $l->login_at ? $l->login_at->toDateString() : null)
                       ->filter()->unique()->values()->all();

        if (empty($userIds) || empty($dates)) {
            return [];
        }

        $rows = DB::table('user_login_logs')
            ->selectRaw('user_id, DATE(login_at) as login_date, MIN(login_at) as first_login, MAX(logout_at) as last_logout')
            ->whereIn('user_id', $userIds)
            ->whereIn(DB::raw('DATE(login_at)'), $dates)
            ->groupByRaw('user_id, DATE(login_at)')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->user_id . '_' . $row->login_date] = $row;
        }

        return $map;
    }

    private function durationBetween($start, $end): string
    {
        if (! $start || ! $end) {
            return '—';
        }
        $s = \Carbon\Carbon::parse($start)->diffInSeconds(\Carbon\Carbon::parse($end));
        $h = (int) floor($s / 3600);
        $m = (int) floor(($s % 3600) / 60);
        return $h . 'h ' . $m . 'm';
    }

    /**
     * Build synthetic attendance rows from active sessions when user_login_logs has no data.
     */
    private function buildSessionFallbackRows(array $validated, string $sort, string $order, int $page, int $perPage): array
    {
        if (! empty($validated['status']) && $validated['status'] !== 'logged_in') {
            return ['data' => [], 'total' => 0, 'last_page' => 1];
        }

        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->get(['id', 'user_id', 'last_activity']);

        if ($sessions->isEmpty()) {
            return ['data' => [], 'total' => 0, 'last_page' => 1];
        }

        $users = User::query()
            ->with('roles:id,name')
            ->whereIn('id', $sessions->pluck('user_id')->unique()->values()->all())
            ->where('status', 'approved')
            ->get()
            ->keyBy('id');

        $from = ! empty($validated['from']) ? \Carbon\Carbon::parse($validated['from'])->startOfDay() : null;
        $to = ! empty($validated['to']) ? \Carbon\Carbon::parse($validated['to'])->endOfDay() : null;
        $roleFilter = (string) ($validated['role'] ?? '');
        $userIdFilter = (int) ($validated['user_id'] ?? 0);

        $rows = collect();
        foreach ($sessions as $session) {
            $user = $users->get((int) $session->user_id);
            if (! $user) {
                continue;
            }

            if ($userIdFilter > 0 && (int) $user->id !== $userIdFilter) {
                continue;
            }
            if ($roleFilter !== '' && ! $user->roles->contains(fn ($r) => (string) $r->name === $roleFilter)) {
                continue;
            }

            $loginAt = \Carbon\Carbon::createFromTimestamp((int) $session->last_activity);
            if ($from && $loginAt->lt($from)) {
                continue;
            }
            if ($to && $loginAt->gt($to)) {
                continue;
            }

            $empNum = $user->employee_number ?: $user->id;
            $employeeId = 'EMP-' . str_pad((string) $empNum, 3, '0', STR_PAD_LEFT);
            $roleLabel = $user->roles
                ->pluck('name')
                ->map(fn ($n) => str_replace('_', ' ', ucwords((string) $n, '_')))
                ->implode(', ');

            $rows->push([
                'id' => null,
                'user_id' => (int) $user->id,
                'employee_name' => (string) ($user->name ?? '—'),
                'employee_id' => $employeeId,
                'role' => $roleLabel !== '' ? $roleLabel : '—',
                'department' => $user->department ?? '—',
                'login_date' => $loginAt->format('d M Y'),
                'login_time' => $loginAt->format('H:i:s'),
                'logout_time' => null,
                'first_login_time' => $loginAt->format('H:i:s'),
                'last_logout_time' => null,
                'duration_text' => 'In Progress',
                'duration_state' => 'in_progress',
                'status' => 'logged_in',
            ]);
        }

        if ($rows->isEmpty()) {
            return ['data' => [], 'total' => 0, 'last_page' => 1];
        }

        $sortField = in_array($sort, ['employee_name', 'employee_id', 'role'], true) ? $sort : 'login_at';
        if ($sortField === 'login_at') {
            $rows = $rows->sortBy('login_date')->values();
        } else {
            $rows = $rows->sortBy($sortField, SORT_NATURAL | SORT_FLAG_CASE)->values();
        }
        if ($order === 'desc') {
            $rows = $rows->reverse()->values();
        }

        $total = $rows->count();
        $paged = $rows->forPage($page, $perPage)->values()->all();
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return ['data' => $paged, 'total' => $total, 'last_page' => $lastPage];
    }
}
