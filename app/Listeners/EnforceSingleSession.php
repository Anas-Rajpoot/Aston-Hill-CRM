<?php

namespace App\Listeners;

use App\Models\SecuritySetting;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * When "prevent_multiple_sessions" is enabled, invalidate all other
 * sessions for this user on login, keeping only the current one.
 */
class EnforceSingleSession
{
    public function handle(Login $event): void
    {
        try {
            $settings = SecuritySetting::current();

            if (! $settings->prevent_multiple_sessions) {
                return;
            }

            $user             = $event->user;
            $currentSessionId = Session::getId();
            $driver           = config('session.driver');

            if ($driver === 'database') {
                // Delete other sessions belonging to this user
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            }

            // For redis/file drivers, a token-based approach or
            // session ID tracking table can be used. For now,
            // database driver support is provided.

        } catch (\Throwable $e) {
            // Silently fail if table doesn't exist yet
        }
    }
}
