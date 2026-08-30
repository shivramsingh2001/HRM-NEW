<?php
// app/Notifications/CustomNotification.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $body;
    protected $data;

    public function __construct(string $title, string $body, array $data = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->body,
            'type' => $this->data['type'] ?? 'general',
            'created_at' => now()->toDateTimeString()
        ], $this->data);
    }
}