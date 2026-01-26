<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class EmailFollowupAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $followupId,
        public string $subject
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'kind' => 'email_followup',
            'title' => 'Email Follow Up Due',
            'message' => $this->subject,
            'url' => route('email-followups.show', $this->followupId),
        ];
    }
}
