<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'));
        
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send notification to a single device
     */
    public function sendToDevice($deviceToken, $title, $body, array $data = [])
    {
        try {
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            
            return [
                'success' => true,
                'message' => 'Notification sent successfully'
            ];
        } catch (MessagingException $e) {
            return [
                'success' => false,
                'message' => 'FCM Error: ' . $e->getMessage()
            ];
        } catch (FirebaseException $e) {
            return [
                'success' => false,
                'message' => 'Firebase Error: ' . $e->getMessage()
            ];
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