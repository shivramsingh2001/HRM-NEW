<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class LocationStatusNotification extends Notification
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
      
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
       
        
        // Send via database and FCM
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
    public function toArray(object $notifiable): array
    {
        $data = [
            'id' => uniqid(),
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
            'read_at' => null,
            'created_at' => now()->toDateTimeString()
        ];


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
            'id' => uniqid(),
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
     * Determine which queues to use for different channels.
     */
    public function viaQueues(): array
    {
        return [
            'database' => 'notifications',
            'fcm' => 'fcm',
            'broadcast' => 'broadcasts',
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e)
    {
       
    }
}