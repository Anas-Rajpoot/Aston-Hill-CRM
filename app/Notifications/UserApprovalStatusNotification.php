<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserApprovalStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $status)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = match ($this->status) {
            'active'   => 'Your account is approved. You can now log in.',
            'pending'  => 'Your account is still pending approval. Please wait.',
            'rejected' => 'Your registration was rejected. Please contact support.',
            default    => 'Your account status was updated.',
        };

        $mail = (new MailMessage)
            ->subject('Account Status Update')
            ->line($message);

        // Optional: login link only if active
        if ($this->status === 'active') {
            $mail->action('Login', route('login'));
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
