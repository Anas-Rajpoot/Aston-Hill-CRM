<?php

namespace App\Listeners;

use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

/**
 * On failed login: increment failed_login_attempts.
 * If max exceeded and lock_after_failed_attempts is enabled, set locked_until.
 */
class TrackFailedLogins
{
    public function handle(Failed $event): void
    {
        try {
            $settings = SecuritySetting::current();

            // Find user by credentials email
            $email = $event->credentials['email'] ?? null;
            if (! $email) {
                return;
            }

            $user = User::where('email', $email)->first();
            if (! $user) {
                return;
            }

            $user->increment('failed_login_attempts');

            if ($settings->lock_after_failed_attempts
                && $user->failed_login_attempts >= $settings->max_login_attempts
            ) {
                $user->update([
                    'locked_until' => now()->addMinutes($settings->lock_duration_minutes),
                ]);
            }
        } catch (\Throwable $e) {
            // Silently fail during migrations
        }
    }
}
