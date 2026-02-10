<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $this->authorizeView();

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

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $sessionIds = DB::table('sessions')->whereIn('id', $items->pluck('session_id')->filter()->values()->all())->pluck('id')->flip();

        $rows = $items->map(function (UserLoginLog $log) use ($sessionIds) {
            return $this->formatRow($log, $sessionIds);
        });

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
     * Filters for dropdowns: users, roles.
     */
    public function filters(Request $request): JsonResponse
    {
        $this->authorizeView();

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

        return response()->json([
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Force logout a single log (session).
     */
    public function forceLogoutLog(Request $request, UserLoginLog $userLoginLog): JsonResponse
    {
        $this->authorizeForceLogout();

        if ($userLoginLog->session_id) {
            DB::table('sessions')->where('id', $userLoginLog->session_id)->delete();
        }

        if (! $userLoginLog->logout_at) {
            $userLoginLog->update(['logout_at' => now()]);
        }

        return response()->json(['message' => 'Session logged out.']);
    }

    /**
     * Force logout user from all sessions.
     */
    public function forceLogoutUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeForceLogout();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        UserLoginLog::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->update(['logout_at' => now()]);

        return response()->json(['message' => 'User logged out from all sessions.']);
    }

    private function authorizeView(): void
    {
        if (! (request()->user()->hasRole('superadmin') || request()->user()->can('view_attendance_logs'))) {
            abort(403, 'Unauthorized.');
        }
    }

    private function authorizeForceLogout(): void
    {
        if (! (request()->user()->hasRole('superadmin') || request()->user()->can('force_logout'))) {
            abort(403, 'Unauthorized.');
        }
    }

    private function formatRow(UserLoginLog $log, $sessionIds): array
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
            'duration_text' => $durationText,
            'duration_state' => $durationState ?? 'normal',
            'status' => $status,
        ];
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
}
