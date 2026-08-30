<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class NotificationService
{
    protected $messaging;
    protected $fcmEnabled;

    public function __construct()
    {
        $this->fcmEnabled = file_exists(storage_path('app/firebase/firebase_credentials.json'));
        
        if ($this->fcmEnabled) {
            try {
                $factory = (new Factory)
                    ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'));
                $this->messaging = $factory->createMessaging();
            } catch (\Exception $e) {
                Log::error('FCM initialization failed: ' . $e->getMessage());
                $this->fcmEnabled = false;
            }
        }
    }

    /**
     * Send notification to a specific user (saves to DB + FCM)
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [])
    {
        try {
            // 1. Save to Database (For the "In-App" list)
            $user->notify(new \App\Notifications\CustomNotification($title, $body, $data));
            
            Log::info('Notification stored in database', [
                'user_id' => $user->id,
                'title' => $title
            ]);

            // 2. Send Push Notification via FCM (if enabled and token exists)
            if ($this->fcmEnabled && $user->fcm_token) {
                $this->sendFcmPush($user, $title, $body, $data);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send FCM push notification only (without DB storage)
     */
    public function sendFcmPush(User $user, string $title, string $body, array $data = [])
    {
        if (!$this->fcmEnabled || !$this->messaging || !$user->fcm_token) {
            return false;
        }

        try {
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData(array_merge([
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default'
                ], $data));

            $this->messaging->send($message);
            
            Log::info('FCM push sent', ['user_id' => $user->id]);
            return true;

        } catch (\Exception $e) {
            Log::error("FCM Error for user {$user->id}: " . $e->getMessage());
            
            // If token is invalid, clear it from DB
            if (str_contains($e->getMessage(), 'NotRegistered') || 
                str_contains($e->getMessage(), 'InvalidRegistration')) {
                $user->update(['fcm_token' => null]);
            }
            
            return false;
        }
    }

    /**
     * Send to multiple users
     */
    public function sendToMultipleUsers($users, string $title, string $body, array $data = [])
    {
        $successCount = 0;
        
        foreach ($users as $user) {
            if ($this->sendToUser($user, $title, $body, $data)) {
                $successCount++;
            }
        }
        
        return $successCount;
    }
}