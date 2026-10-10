<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Shift swap / change request and roster-change notifications: in-app (bell)
 * + push, same wording on both. Built by App\Services\Shift\ShiftNotificationService.
 */
class ShiftNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $message,
        private array $data = []
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray($notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
            'type' => 'shift',
            'created_at' => now()->toDateTimeString(),
        ], $this->data);
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'data' => array_map('strval', array_filter(array_merge(['type' => 'shift', 'click_action' => 'SHIFT'], $this->data), fn ($v) => $v !== null && ! is_array($v))),
        ];
    }
}
