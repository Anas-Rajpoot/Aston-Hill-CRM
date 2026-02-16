<?php

namespace App\Jobs;

use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daily job: batch-flag users whose passwords have expired.
 *
 * Sets must_change_password = true on users whose password_changed_at
 * is older than password_expiry_days. Skips super admins.
 */
class CheckExpiredPasswords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $settings = SecuritySetting::current();

        if ($settings->password_expiry_days <= 0) {
            return;
        }

        $expiryDate = now()->subDays($settings->password_expiry_days);

        // Flag users whose password has expired and who haven't already been flagged.
        // Exclude super admins who are exempt from password expiry.
        $count = User::query()
            ->where('must_change_password', false)
            ->where('status', 'approved')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'superadmin'))
            ->where(function ($q) use ($expiryDate) {
                $q->whereNull('password_changed_at')
                  ->orWhere('password_changed_at', '<', $expiryDate);
            })
            ->update(['must_change_password' => true]);

        if ($count > 0) {
            Log::info("CheckExpiredPasswords: flagged {$count} user(s) with expired passwords.");
        }
    }
}
