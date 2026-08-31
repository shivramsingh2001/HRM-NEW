<?php
// app/Channels/FcmChannel.php

namespace App\Channels;

use App\Services\FirebaseService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FcmChannel
{
    public function __construct(protected FirebaseService $firebaseService)
    {
    }

    /**
     * Send the given notification and prune any tokens that come back
     * unregistered.
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toFcm') || !$this->firebaseService->isReady()) {
            return;
        }

        $tokens = $notifiable->fcm_tokens ?? [];
        if (empty($tokens)) {
            return;
        }

        $fcmData = $notification->toFcm($notifiable);
        $dead = [];

        foreach ($tokens as $tokenData) {
            $token = is_array($tokenData) ? ($tokenData['token'] ?? null) : $tokenData;
            if (!$token) {
                continue;
            }

            $result = $this->firebaseService->sendToDevice(
                $token,
                $fcmData['title'] ?? '',
                $fcmData['body'] ?? '',
                $fcmData['data'] ?? []
            );

            if (!empty($result['invalid_token'])) {
                $dead[] = $token;
            }
        }

        if ($dead) {
            $this->pruneTokens($notifiable, $dead);
        }
    }

    private function pruneTokens($notifiable, array $dead): void
    {
        try {
            $remaining = collect($notifiable->fcm_tokens ?? [])
                ->reject(function ($tokenData) use ($dead) {
                    $token = is_array($tokenData) ? ($tokenData['token'] ?? null) : $tokenData;
                    return $token && in_array($token, $dead, true);
                })
                ->values()
                ->all();

            $notifiable->forceFill(['fcm_tokens' => $remaining])->save();

            Log::info('FcmChannel: pruned unregistered device tokens', [
                'notifiable_id' => $notifiable->id ?? null,
                'pruned' => count($dead),
            ]);
        } catch (\Throwable $e) {
            Log::error('FcmChannel: failed to prune tokens: ' . $e->getMessage());
        }
    }
}
