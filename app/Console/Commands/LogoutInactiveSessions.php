<?php

namespace App\Console\Commands;

use App\Models\UserLoginLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Log out sessions that have been inactive for 2+ hours.
 * Deletes session rows and sets logout_at on corresponding user_login_logs.
 */
class LogoutInactiveSessions extends Command
{
    protected $signature = 'sessions:logout-inactive {--hours=2 : Inactivity threshold in hours}';

    protected $description = 'Force logout sessions inactive for more than N hours (default 2)';

    public function handle(): int
    {
        $hours = (float) $this->option('hours');
        $cutoff = now()->subHours($hours)->timestamp;

        $sessionIds = DB::table('sessions')
            ->where('last_activity', '<', $cutoff)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return self::SUCCESS;
        }

        $updated = UserLoginLog::whereIn('session_id', $sessionIds->all())
            ->whereNull('logout_at')
            ->update(['logout_at' => now()]);

        DB::table('sessions')->whereIn('id', $sessionIds->all())->delete();

        $this->info("Logged out {$updated} session(s); removed " . $sessionIds->count() . " session(s).");

        return self::SUCCESS;
    }
}
