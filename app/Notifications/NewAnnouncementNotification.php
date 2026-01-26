<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public int $announcementId, public string $title)
    {
        
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Announcement')
            ->line('New Announcement: ' . $this->title)
                    ->action('View Announcement', route('announcements.show', $this->announcementId));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'announcement',
            'title' => 'New Announcement',
            'message' => $this->title,
            'url' => route('announcements.show', $this->announcementId),
        ];
    }

    public function toDatabase($notifiable)
    {
        return [
            'kind' => 'announcement',
            'title' => 'New Announcement',
            'message' => $this->title,
            'url' => route('announcements.show', $this->announcementId),
        ];
    }
}
