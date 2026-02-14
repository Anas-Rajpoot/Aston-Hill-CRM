<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app notification stored in the `notifications` table.
 * Shown in the bell icon / notification panel.
 *
 * Data stored: trigger_key, title, message, url, module, is_sla.
 */
class InAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string  $triggerKey,
        public string  $title,
        public string  $message,
        public ?string $url = null,
        public ?string $module = null,
        public bool    $isSla = false,
    ) {}

    /** Only the database channel — this is an in-app notification. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Payload written to the `data` JSON column. */
    public function toArray(object $notifiable): array
    {
        return [
            'trigger_key' => $this->triggerKey,
            'title'       => $this->title,
            'message'     => $this->message,
            'url'         => $this->url,
            'module'      => $this->module,
            'is_sla'      => $this->isSla,
            'kind'        => $this->isSla ? 'sla' : 'general',
            'type'        => str_replace('_', ' ', $this->triggerKey),
        ];
    }
}
