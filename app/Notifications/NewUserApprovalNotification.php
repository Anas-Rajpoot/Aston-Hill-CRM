<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class NewUserApprovalNotification extends Notification
{
    use Queueable;

    public User $user;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail','database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $editUrl = url('/users/' . $this->user->id . '/edit');
        return (new MailMessage)
            ->subject('New User Pending Approval')
            ->line('A new user has registered and is waiting for approval.')
            ->line('Name: ' . $this->user->name)
            ->line('Email: ' . $this->user->email)
            ->action('Edit User', $editUrl);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'user_approval',
            'title' => 'New user pending approval',
            'user_id' => $this->user->id,
            'name'    => $this->user->name,
            'email'   => $this->user->email,
            'url'     => '/users/' . $this->user->id . '/edit',
            'message' => $this->user->name . " registered and is waiting for approval."
        ];
    }
}
