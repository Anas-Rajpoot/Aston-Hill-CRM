<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * On successful login: reset failed_login_attempts counter and clear locked_until.
 */
class ResetFailedLoginsOnSuccess
{
    public function handle(Login $event): void
    {
        try {
            $user = $event->user;

            if ($user->failed_login_attempts > 0 || $user->locked_until) {
                $user->update([
                    'failed_login_attempts' => 0,
                    'locked_until'          => null,
                ]);
            }
        } catch (\Throwable $e) {
            // Silently fail during migrations
        }
    }
}
