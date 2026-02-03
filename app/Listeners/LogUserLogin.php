<?php

namespace App\Listeners;

use App\Jobs\ProcessLoginLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

/**
 * Dispatches login log processing to a queue so the login HTTP response is not blocked.
 * Ensure a queue worker is running in production (e.g. php artisan queue:work).
 */
class LogUserLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        ProcessLoginLog::dispatch(
            (int) $user->id,
            session()->getId(),
            request()->ip() ?? '',
            (string) Request::userAgent()
        );
    }
}
