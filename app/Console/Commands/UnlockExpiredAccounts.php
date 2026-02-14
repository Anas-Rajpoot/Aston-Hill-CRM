<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Scheduled each minute: clear locked_until for users whose lockout has expired.
 */
class UnlockExpiredAccounts extends Command
{
    protected $signature = 'security:unlock-expired';

    protected $description = 'Unlock user accounts whose lock duration has expired';

    public function handle(): int
    {
        $count = User::where('locked_until', '<=', now())
            ->whereNotNull('locked_until')
            ->update([
                'locked_until'          => null,
                'failed_login_attempts' => 0,
            ]);

        if ($count > 0) {
            $this->info("Unlocked {$count} expired account(s).");
        }

        return self::SUCCESS;
    }
}
