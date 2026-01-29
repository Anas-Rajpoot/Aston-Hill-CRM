<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class LeadSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public LeadSubmission $leadSubmission) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'kind' => 'lead submission',
            'title' => 'New Lead Submission is Submitted',
            'message' => 'Lead #' . $this->leadSubmission->id . ' has been submitted',
            'url' => route('lead-submissions.show', $this->leadSubmission),
        ];
    }
}
