<?php
// app/Channels/FcmChannel.php

namespace App\Channels;

use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $fcmData = $notification->toFcm($notifiable);
        
        if (empty($notifiable->fcm_tokens)) {
            return;
        }

        foreach ($notifiable->fcm_tokens as $tokenData) {
            $this->firebaseService->sendToDevice(
                $tokenData['token'],
                $fcmData['title'],
                $fcmData['body'],
                $fcmData['data'] ?? []
            );
        }
    }
}