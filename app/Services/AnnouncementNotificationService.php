<?php
// app/Services/AnnouncementNotificationService.php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Log;

class AnnouncementNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Notify every active employee in the tenant (except the creator)
     * that a new announcement was posted.
     */
    public function notifyAnnouncementCreated(Announcement $announcement)
    {
        try {
            $recipients = User::where('tenant_id', $announcement->tenant_id)
                ->where('status', 1)
                ->where('id', '!=', $announcement->user_id)
                ->get();

            if ($recipients->isEmpty()) {
                return false;
            }

            $title = '📢 New Announcement';
            $body = $announcement->title;
            $data = [
                'announcement_id' => $announcement->id,
                'requires_acknowledgment' => (bool) $announcement->acknowledge,
                'type' => 'announcement_created',
            ];

            foreach ($recipients as $user) {
                $this->sendNotification($user, $title, $body, $data);
                $user->notify(new AnnouncementNotification($announcement));
            }

            Log::info('Announcement creation notifications sent', [
                'announcement_id' => $announcement->id,
                'recipient_count' => $recipients->count(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send announcement creation notifications', [
                'error' => $e->getMessage(),
                'announcement_id' => $announcement->id,
            ]);
            return false;
        }
    }

    /**
     * Core method to send FCM push notification to a user's active devices.
     */
    private function sendNotification($user, $title, $body, $data = [])
    {
        $tokens = $user->fcm_tokens ?? [];

        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($tokens) || empty($tokens)) {
            return false;
        }

        $successCount = 0;

        foreach ($tokens as $tokenData) {
            $token = is_array($tokenData) ? ($tokenData['token'] ?? '') : $tokenData;

            if (empty($token)) {
                continue;
            }

            $result = $this->firebaseService->sendToDevice($token, $title, $body, $data);

            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }
}
