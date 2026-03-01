<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * Sends a password reset link email for a specific user email.
 * Queued to keep reset-password API responses fast.
 */
class SendUserPasswordResetLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $email
    ) {}

    public function handle(): void
    {
        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('Password reset link dispatch did not send.', [
                'email' => $this->email,
                'status' => $status,
            ]);
        }
    }
}

