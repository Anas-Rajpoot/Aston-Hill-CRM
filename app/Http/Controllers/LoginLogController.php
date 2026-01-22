<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserLoginLog;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LoginLogsExport;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Yajra\DataTables\Facades\DataTables;
use App\Support\Format;


class LoginLogController extends Controller
{
    public function index(Request $request)
    {
        $users = User::select('id','name','email')->orderBy('name')->get();
        $roles = DB::table('roles')->select('id','name')->orderBy('name')->get();

        return view('login-logs.index', compact('users', 'roles'));
    }

    public function datatable(Request $request)
    {
        $query = UserLoginLog::query()
            ->with(['user:id,name,email'])
            ->select('user_login_logs.*');

        // Filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('role')) {
            $role = $request->role;
            $query->whereHas('user.roles', fn($q) => $q->where('name', $role));
        }

        if ($request->filled('from')) {
            $query->where('login_at', '>=', $request->from.' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('login_at', '<=', $request->to.' 23:59:59');
        }

        if ($request->filled('status')) {
            if ($request->status === 'online') {
                $query->whereNull('logout_at');
            } elseif ($request->status === 'offline') {
                $query->whereNotNull('logout_at');
            }
        }

        return DataTables::eloquent($query)

            ->editColumn('login_at', function (UserLoginLog $log) {
                $tz = Format::tzForLog($log);
                return Format::dt($log->login_at, $tz);
            })

            ->editColumn('logout_at', function (UserLoginLog $log) {
                $tz = Format::tzForLog($log);
                return Format::dt($log->logout_at, $tz);
            })
            ->addColumn('user', function (UserLoginLog $log) {
                return $log->user
                    ? $log->user->name . ' (' . $log->user->email . ')'
                    : '-';
            })
            ->addColumn('role', function (UserLoginLog $log) {
                if (!$log->user) return '-';
                return $log->user->getRoleNames()->implode(', ') ?: '-';
            })
            ->addColumn('duration', function (UserLoginLog $log) {
                $sec = $log->active_seconds;
                $h = floor($sec / 3600);
                $m = floor(($sec % 3600) / 60);
                $s = $sec % 60;
                return sprintf('%02dh %02dm %02ds', $h, $m, $s);
            })
            ->addColumn('status_badge', function (UserLoginLog $log) {
                return $log->logout_at ? 'Offline' : 'Online';
            })
            ->addColumn('actions', function (UserLoginLog $log) {
                $timeline = route('login-logs.timeline', $log->user_id);

                $forceByLog = route('login-logs.force-logout-log', $log->id);
                $forceByUser = route('login-logs.force-logout-user', $log->user_id);

                $btn = '<div class="flex gap-2 flex-wrap">';

                $btn .= '<a class="px-3 py-1 rounded bg-gray-800 text-white text-xs" href="'.$timeline.'">Timeline</a>';

                if (!$log->logout_at) {
                    $btn .= '<form method="POST" action="'.$forceByLog.'" onsubmit="return confirm(\'Force logout this session?\')" style="display:inline">';
                    $btn .= csrf_field().method_field('POST');
                    $btn .= '<button class="px-3 py-1 rounded bg-red-600 text-white text-xs">Force Logout (Session)</button>';
                    $btn .= '</form>';

                    $btn .= '<form method="POST" action="'.$forceByUser.'" onsubmit="return confirm(\'Force logout user from all sessions?\')" style="display:inline">';
                    $btn .= csrf_field().method_field('POST');
                    $btn .= '<button class="px-3 py-1 rounded bg-red-800 text-white text-xs">Force Logout (User)</button>';
                    $btn .= '</form>';
                }

                $btn .= '</div>';

                return $btn;
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function data(Request $request)
    {
        $query = UserLoginLog::with('user');

        // Filters
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->role) {
            $query->where('role', $request->role);
        }

        if ($request->from && $request->to) {
            $query->whereBetween('login_at', [
                $request->from.' 00:00:00',
                $request->to.' 23:59:59',
            ]);
        }

        return datatables()->of($query)
            ->addColumn('user', fn($r) => $r->user->name)
            ->addColumn('email', fn($r) => $r->user->email)

            ->editColumn('login_at', fn($r) => $r->login_at?->format('d-M-Y h:i A') ?? '—')
            ->editColumn('logout_at', fn($r) => $r->logout_at?->format('d-M-Y h:i A') ?? '—')

            ->make(true);
    }

    public function timeline(User $user)
    {
        $logs = UserLoginLog::where('user_id', $user->id)
            ->latest('login_at')
            ->paginate(20);

        return view('login-logs.timeline', compact('user', 'logs'));
    }

    public function exportCsv(Request $request)
    {
        $fileName = 'login_logs_'.now()->format('Ymd_His').'.csv';

        $query = UserLoginLog::query()->with('user');

        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('from')) $query->where('login_at', '>=', $request->from.' 00:00:00');
        if ($request->filled('to')) $query->where('login_at', '<=', $request->to.' 23:59:59');

        $logs = $query->latest('login_at')->limit(5000)->get();

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename={$fileName}",
        ];

        $columns = ['User', 'Email', 'Login At', 'Logout At', 'Duration Seconds', 'IP', 'Country', 'Suspicious', 'Reason'];

        $callback = function () use ($logs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($logs as $log) {
                fputcsv($file, [
                    optional($log->user)->name,
                    optional($log->user)->email,
                    Format::dt($log->login_at, $log->user?->timezone),
                    Format::dt($log->logout_at, $log->user?->timezone),
                    $log->active_seconds,
                    $log->ip_address,
                    $log->country,
                    $log->is_suspicious ? 'YES' : 'NO',
                    $log->suspicious_reason,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function forceLogoutLog(UserLoginLog $log)
    {
        if (!$log->session_id) {
            return back()->with('error', 'Session not found.');
        }

        DB::table('sessions')->where('id', $log->session_id)->delete();

        if (!$log->logout_at) {
            $log->update(['logout_at' => now()]);
        }

        return back()->with('status', 'User has been logged out.');
    }

    public function forceLogoutUser(User $user)
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();

        UserLoginLog::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->update(['logout_at' => now()]);

        return back()->with('status', 'User logged out from all sessions.');
    }

    public function dailySummary()
    {
        $from = request('from', now()->subDays(7)->toDateString());
        $to   = request('to', now()->toDateString());

        $rows = UserLoginLog::selectRaw("
                user_id,
                DATE(login_at) as day,
                SUM(TIMESTAMPDIFF(SECOND, login_at, COALESCE(logout_at, NOW()))) as total_seconds
            ")
            ->whereBetween('login_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('user_id', 'day')
            ->get();

        return $rows;
    }
}
