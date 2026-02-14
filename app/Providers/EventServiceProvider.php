<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \Illuminate\Auth\Events\Login::class => [
            \App\Listeners\LogUserLogin::class,
            \App\Listeners\ResetFailedLoginsOnSuccess::class,
            \App\Listeners\EnforceSingleSession::class,
        ],
        \Illuminate\Auth\Events\Logout::class => [
            \App\Listeners\LogUserLogout::class,
        ],
        \Illuminate\Auth\Events\Failed::class => [
            \App\Listeners\TrackFailedLogins::class,
        ],
    ];
}
