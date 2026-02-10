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

