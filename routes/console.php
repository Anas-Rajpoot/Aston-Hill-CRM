<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Auto logout sessions inactive for 2 hours
Schedule::command('sessions:logout-inactive', ['--hours' => 2])
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Unlock accounts whose lockout has expired
Schedule::command('security:unlock-expired')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Purge form drafts older than 30 days
Schedule::command('drafts:purge-expired')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// SLA Escalation check — fires queued job every 5 minutes
Schedule::job(new \App\Jobs\CheckEscalationJob())
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Flag users whose passwords have expired (daily at 2 AM)
Schedule::job(new \App\Jobs\CheckExpiredPasswords())
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->onOneServer();

