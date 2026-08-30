<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class LocationOffNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $locationData;
    protected $statusType;

    /**
     * Create a new notification instance.
     */
    public function __construct($user, $locationData, $statusType)
    {
        $this->user = $user;
        $this->locationData = $locationData;
        $this->statusType = $statusType; // 'off' only now
        
        Log::info('LocationStatusNotification created', [
            'user_id' => $user->id ?? null,
            'user_name' => $user->name ?? null,
            'status_type' => $statusType,
            'location_data' => $locationData
        ]);
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        Log::info('LocationStatusNotification via method called', [
            'notifiable_id' => $notifiable->id,
            'notifiable_email' => $notifiable->email,
            'channels' => ['database', 'fcm']
        ]);
        
        // Send via database and FCM
        $channels = ['database'];
        
        // Add FCM if available (handled by your FCM channel)
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
    public function toArray(object $notifiable): array
    {
        $data = [
            'title' => '📍 Location Disabled',
            'message' => "{$this->user->name} has turned off location services",
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'type' => 'location_off_alert',
            'location' => [
                'lat' => $this->locationData['latitude'] ?? null,
                'lng' => $this->locationData['longitude'] ?? null,
                'accuracy' => $this->locationData['accuracy'] ?? null,
                'reason' => $this->locationData['reason'] ?? 'Not provided',
                'battery' => $this->locationData['battery_level'] ?? null
            ],
            'time' => now()->toDateTimeString(),
            'tenant_id' => $this->user->tenant_id,
            'priority' => 'high',
            'read_at' => null
        ];

        Log::info('LocationStatusNotification toArray called', [
            'notifiable_id' => $notifiable->id,
            'notification_data' => $data
        ]);

        return $data;
    }

    /**
     * Get the FCM representation of the notification.
     */
    public function toFcm(object $notifiable): array
    {
        $data = [
            'title' => '📍 Location Disabled',
            'body' => "{$this->user->name} has turned off location services",
            'user_id' => (string) $this->user->id,
            'user_name' => $this->user->name,
            'type' => 'location_off_alert',
            'latitude' => (string) ($this->locationData['latitude'] ?? ''),
            'longitude' => (string) ($this->locationData['longitude'] ?? ''),
            'accuracy' => (string) ($this->locationData['accuracy'] ?? ''),
            'reason' => (string) ($this->locationData['reason'] ?? ''),
            'battery' => (string) ($this->locationData['battery_level'] ?? ''),
            'timestamp' => (string) now()->timestamp,
            'click_action' => 'LOCATION_ALERT',
            'priority' => 'high'
        ];

        Log::info('LocationStatusNotification toFcm called', [
            'notifiable_id' => $notifiable->id,
            'fcm_data' => $data
        ]);

        return [
            'notification' => [
                'title' => $data['title'],
                'body' => $data['body'],
                'sound' => 'default'
            ],
            'data' => $data,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'icon' => 'ic_location_off',
                    'color' => '#f44336',
                    'priority' => 'high',
                    'sound' => 'default',
                    'click_action' => 'LOCATION_ALERT'
                ]
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                        'category' => 'LOCATION_ALERT'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $broadcastData = [
            'title' => '📍 Location Disabled',
            'message' => "{$this->user->name} has turned off location services",
            'type' => 'location_off_alert',
            'user_name' => $this->user->name,
            'user_id' => $this->user->id,
            'time' => now()->diffForHumans(),
            'location' => [
                'lat' => $this->locationData['latitude'] ?? null,
                'lng' => $this->locationData['longitude'] ?? null
            ]
        ];

        Log::info('LocationStatusNotification toBroadcast called', [
            'notifiable_id' => $notifiable->id,
            'broadcast_data' => $broadcastData
        ]);

        return new BroadcastMessage($broadcastData);
    }

    /**
     * Get the mail representation of the notification (optional).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $locationText = '';
        if ($this->locationData['latitude'] && $this->locationData['longitude']) {
            $locationText = "Location: ({$this->locationData['latitude']}, {$this->locationData['longitude']})";
        }

        return (new MailMessage)
            ->subject('📍 Location Services Disabled - ' . $this->user->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($this->user->name . ' has turned off location services.')
            ->line('Time: ' . now()->format('d M Y h:i A'))
            ->line('Reason: ' . ($this->locationData['reason'] ?? 'Not provided'))
            ->line($locationText)
            ->line('Battery Level: ' . ($this->locationData['battery_level'] ?? 'Unknown') . '%')
            ->action('View Details', url('/dashboard'))
            ->line('Please follow up with the employee if necessary.');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e)
    {
        Log::error('LocationStatusNotification failed to send', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $this->user->id ?? null,
            'status_type' => $this->statusType
        ]);
    }
}