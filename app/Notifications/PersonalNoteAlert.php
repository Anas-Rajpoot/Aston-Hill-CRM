<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class PersonalNoteAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $noteId,
        public string $title
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'kind' => 'personal_note',
            'title' => 'Personal Note Reminder',
            'message' => $this->title,
            'url' => route('personal-notes.show', $this->noteId),
        ];
    }
}
