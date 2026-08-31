<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected $messaging;

    /** True only when the credentials file was present and the SDK initialised. */
    protected bool $ready = false;

    public function __construct()
    {
        $credentialsPath = storage_path('app/firebase/firebase_credentials.json');

        if (!is_file($credentialsPath)) {
            Log::warning('FirebaseService: credentials file missing, push notifications disabled', [
                'path' => $credentialsPath,
            ]);
            return;
        }

        try {
            $this->messaging = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->createMessaging();
            $this->ready = true;
        } catch (\Throwable $e) {
            Log::error('FirebaseService: failed to initialise, push notifications disabled', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    /**
     * Send notification to a single device.
     * Returns ['success' => bool, 'message' => string, 'invalid_token' => bool].
     */
    public function sendToDevice($deviceToken, $title, $body, array $data = [])
    {
        if (!$this->ready) {
            return ['success' => false, 'message' => 'Firebase not configured', 'invalid_token' => false];
        }

        try {
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);

            return ['success' => true, 'message' => 'Notification sent successfully', 'invalid_token' => false];
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // Token is unregistered — caller should prune it.
            return ['success' => false, 'message' => 'Unregistered token: ' . $e->getMessage(), 'invalid_token' => true];
        } catch (MessagingException $e) {
            return ['success' => false, 'message' => 'FCM Error: ' . $e->getMessage(), 'invalid_token' => false];
        } catch (FirebaseException $e) {
            return ['success' => false, 'message' => 'Firebase Error: ' . $e->getMessage(), 'invalid_token' => false];
        }
    }

    /**
     * Send notification to multiple devices
     */
    public function sendToMultipleDevices(array $deviceTokens, $title, $body, array $data = [])
    {
        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $result = $this->messaging->sendMulticast($message, $deviceTokens);
            
            return [
                'success' => true,
                'success_count' => $result->successes()->count(),
                'failure_count' => $result->failures()->count(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}