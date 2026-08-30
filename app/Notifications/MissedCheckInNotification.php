<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class MissedCheckInNotification extends Notification
{
    use Queueable;

    protected $user;
    protected $shift;
    protected $graceMinutes;
    protected $currentTime;

    /**
     * Create a new notification instance.
     */
    public function __construct($user, $shift, $graceMinutes)
    {
        $this->user = $user;
        $this->shift = $shift;
        $this->graceMinutes = $graceMinutes;
        $this->currentTime = now();
        
       
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];
        
        // Add FCM if available
        if (config('services.fcm.enabled', true)) {
            $channels[] = 'fcm';
        }
        
        // Add broadcast for real-time updates (optional)
        if (config('app.env') !== 'production') {
            $channels[] = 'broadcast';
        }
        
        return $channels;
    }

    /**
     * Get the array representation of the notification (for database).
     */
    public function toArray($notifiable): array
    {
        $isSelf = $notifiable->id == $this->user->id;
        
        $message = $isSelf 
            ? "You haven't checked in today. Your shift '{$this->shift->name}' started at {$this->shift->start_time} with {$this->graceMinutes} minutes grace period."
            : "{$this->user->name} hasn't checked in today. Shift '{$this->shift->name}' started at {$this->shift->start_time} with {$this->graceMinutes} minutes grace period.";

        return [
            'id' => uniqid(),
            'title' => '⚠️ Missed Check-In Alert',
            'message' => $message,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'shift_id' => $this->shift->id,
            'shift_name' => $this->shift->name,
            'shift_start' => $this->shift->start_time,
            'shift_end' => $this->shift->end_time,
            'grace_minutes' => $this->graceMinutes,
            'current_time' => $this->currentTime->toDateTimeString(),
            'date' => $this->currentTime->toDateString(),
            'type' => 'missed_checkin',
            'priority' => 'high',
            'is_self' => $isSelf,
            'read_at' => null,
            'created_at' => now()->toDateTimeString()
        ];
    }

    /**
     * Get the FCM representation of the notification.
     */
    public function toFcm($notifiable): array
    {
        $isSelf = $notifiable->id == $this->user->id;
        
        $message = $isSelf 
            ? "You haven't checked in today. Your shift '{$this->shift->name}' started at {$this->shift->start_time}"
            : "{$this->user->name} hasn't checked in today. Shift '{$this->shift->name}' started at {$this->shift->start_time}";

        return [
            'notification' => [
                'title' => '⚠️ Missed Check-In Alert',
                'body' => $message,
                'sound' => 'default'
            ],
            'data' => [
                'type' => 'missed_checkin',
                'user_id' => (string) $this->user->id,
                'user_name' => $this->user->name,
                'shift_id' => (string) $this->shift->id,
                'shift_name' => $this->shift->name,
                'shift_start' => $this->shift->start_time,
                'grace_minutes' => (string) $this->graceMinutes,
                'date' => $this->currentTime->toDateString(),
                'timestamp' => (string) $this->currentTime->timestamp,
                'click_action' => 'MISSED_CHECKIN',
                'priority' => 'high'
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'icon' => 'ic_alert',
                    'color' => '#f44336',
                    'priority' => 'high',
                    'sound' => 'default',
                    'click_action' => 'MISSED_CHECKIN'
                ]
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                        'category' => 'MISSED_CHECKIN'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        $isSelf = $notifiable->id == $this->user->id;
        
        return new BroadcastMessage([
            'id' => uniqid(),
            'title' => '⚠️ Missed Check-In Alert',
            'message' => $isSelf 
                ? "You haven't checked in today."
                : "{$this->user->name} hasn't checked in today.",
            'type' => 'missed_checkin',
            'user_name' => $this->user->name,
            'user_id' => $this->user->id,
            'shift_name' => $this->shift->name,
            'time' => now()->diffForHumans()
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $isSelf = $notifiable->id == $this->user->id;
        
        $subject = $isSelf 
            ? '⚠️ You missed your check-in today'
            : '⚠️ ' . $this->user->name . ' missed check-in today';

        $greeting = $isSelf 
            ? 'Hello ' . $notifiable->name . '!'
            : 'Hello ' . $notifiable->name . ',';

        $lines = [];
        
        if ($isSelf) {
            $lines[] = "You haven't checked in for your shift '{$this->shift->name}' today.";
            $lines[] = "Your shift was scheduled to start at {$this->shift->start_time} with a {$this->graceMinutes} minute grace period.";
            $lines[] = "Please check in immediately or contact your reporting head.";
        } else {
            $lines[] = "{$this->user->name} hasn't checked in for their shift '{$this->shift->name}' today.";
            $lines[] = "The shift was scheduled to start at {$this->shift->start_time} with a {$this->graceMinutes} minute grace period.";
            $lines[] = "Please follow up with the employee.";
        }

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting);
        
        foreach ($lines as $line) {
            $mail->line($line);
        }
        
        $mail->line('Date: ' . $this->currentTime->format('d M Y'))
            ->line('Current Time: ' . $this->currentTime->format('h:i A'))
            ->action('View Dashboard', url('/dashboard'))
            ->line('Thank you for using our application!');

        return $mail;
    }

    /**
     * Determine which queues to use for different channels.
     */
    public function viaQueues(): array
    {
        return [
            'database' => 'notifications',
            'fcm' => 'fcm',
            'mail' => 'emails',
            'broadcast' => 'broadcasts',
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e)
    {
        Log::error('MissedCheckInNotification failed to send', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $this->user->id ?? null,
            'shift_id' => $this->shift->id ?? null
        ]);
    }
}