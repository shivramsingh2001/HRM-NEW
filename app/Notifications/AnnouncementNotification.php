<?php
// app/Notifications/AnnouncementNotification.php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class AnnouncementNotification extends Notification
{
    use Queueable;

    protected $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $creator = $this->announcement->user;

        return [
            'title' => '📢 New Announcement',
            'message' => $this->announcement->title,
            'type' => 'announcement',
            'announcement_id' => $this->announcement->id,
            'requires_acknowledgment' => (bool) $this->announcement->acknowledge,
            'created_by_name' => $creator->name ?? 'Admin',
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
