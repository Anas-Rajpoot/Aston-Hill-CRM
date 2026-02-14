<?php

namespace App\Console\Commands;

use App\Models\FormDraft;
use Illuminate\Console\Command;

class PurgeExpiredDrafts extends Command
{
    protected $signature = 'drafts:purge-expired';
    protected $description = 'Delete form drafts older than 30 days';

    public function handle(): int
    {
        $count = FormDraft::purgeExpired();
        $this->info("Purged {$count} expired draft(s).");
        return self::SUCCESS;
    }
}
