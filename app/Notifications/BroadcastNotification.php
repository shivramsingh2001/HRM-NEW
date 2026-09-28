<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Channels\SmsChannel;
use App\Models\Broadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Dual-write delivery event for the Broadcast Notification module.
 * App\Services\Broadcast\BroadcastComposerService creates one
 * App\Models\BroadcastRecipient row per recipient (the module's own source
 * of truth for stats/read-tracking) and ALSO fires this Notification class
 * per recipient so the send shows up for free in the existing mobile
 * `notifications` table/API and (per the broadcast's own `channels` picks)
 * push/email/SMS.
 *
 * toArray() intentionally mirrors AnnouncementNotification's exact
 * title/message/type key shape — the mobile API's NotificationController
 * reads those generically, so a different shape here would render blank
 * fields for broadcast rows there.
 */
class BroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(protected Broadcast $broadcast)
    {
    }

    public function via($notifiable): array
    {
        $channels = ['database'];
        $picked = $this->broadcast->channels ?? [];

        if (in_array('fcm', $picked, true)) {
            $channels[] = FcmChannel::class;
        }
        if (in_array('email', $picked, true)) {
            $channels[] = 'mail';
        }
        if (in_array('sms', $picked, true)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->broadcast->title,
            'message' => $this->broadcast->body,
            'type' => 'broadcast',
            'broadcast_id' => $this->broadcast->id,
            'action_url' => $this->broadcast->action_url,
            'action_label' => $this->broadcast->action_label,
            'priority' => $this->broadcast->priority,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => $this->broadcast->title,
            'body' => $this->broadcast->body,
            'data' => [
                'type' => 'broadcast',
                'broadcast_id' => (string) $this->broadcast->id,
                'action_url' => (string) ($this->broadcast->action_url ?? ''),
                'click_action' => 'BROADCAST_DETAILS',
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->broadcast->title)
            ->greeting('Hello ' . ($notifiable->name ?? '') . ',')
            ->line($this->broadcast->body);

        if ($this->broadcast->action_url) {
            $mail->action($this->broadcast->action_label ?: 'View details', $this->broadcast->action_url);
        }

        return $mail;
    }

    /**
     * See App\Channels\SmsChannel — this string must fit the tenant's
     * registered DLT template (broadcast SMS is a no-op entirely until an
     * operator configures `DLT_TEMPLATE_ID_BROADCAST`), so it's kept short
     * and generic rather than echoing the full free-text body.
     */
    public function toSms($notifiable): string
    {
        return $this->broadcast->title . ': ' . Str::limit($this->broadcast->body, 100);
    }
}
