<?php

namespace App\Listeners;

use App\Jobs\ProcessLoginLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

/**
 * Write login log immediately so attendance log is reliable even when no queue worker is running.
 */
class LogUserLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        ProcessLoginLog::dispatchSync(
            (int) $user->id,
            session()->getId(),
            request()->ip() ?? '',
            (string) Request::userAgent()
        );
    }
}
