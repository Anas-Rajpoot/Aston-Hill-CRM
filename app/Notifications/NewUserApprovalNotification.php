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
        return (new MailMessage)
            ->subject('New User Pending Approval')
            ->line('A new user has registered and is waiting for approval.')
            ->line('Name: ' . $this->user->name)
            ->line('Email: ' . $this->user->email)
            ->action(
                'Review User',
                route('login', [
                    'redirect' => url()->signedRoute('super-admin.users.review', ['user' => $this->user->id])
                ])
            );
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
            'url'     => route('super-admin.users.review', $this->user->id),
            'message' => $this->user->name . " registered and is waiting for approval."
        ];
    }
}
