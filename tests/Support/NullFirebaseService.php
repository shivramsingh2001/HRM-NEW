<?php

namespace Tests\Support;

use App\Services\FirebaseService;

/**
 * A FirebaseService that can NEVER reach Firebase.
 *
 * The tests run against the shared dev database, which holds real users with real
 * device tokens (27 at the time of writing), and this machine has the real
 * firebase_credentials.json — so without this stub an ordinary feature test that
 * approves an expense could push "Expense approved" to a real person's phone.
 * Bound in Tests\TestCase::setUp(); it records what WOULD have been sent so a test
 * can assert on notification behaviour without any network call.
 */
class NullFirebaseService extends FirebaseService
{
    /** @var array<int, array{token:string, title:string, body:string, data:array}> */
    public array $sent = [];

    public function __construct()
    {
        // Deliberately NOT calling parent::__construct(): it loads the credentials file and boots the SDK.
    }

    public function isReady(): bool
    {
        return false;
    }

    public function sendToDevice($deviceToken, $title, $body, array $data = [])
    {
        $this->sent[] = ['token' => (string) $deviceToken, 'title' => (string) $title, 'body' => (string) $body, 'data' => $data];

        return ['success' => false, 'message' => 'Firebase disabled in tests', 'invalid_token' => false];
    }

    public function sendToMultipleDevices(array $deviceTokens, $title, $body, array $data = [])
    {
        foreach ($deviceTokens as $token) {
            $this->sendToDevice($token, $title, $body, $data);
        }

        return ['success' => false, 'message' => 'Firebase disabled in tests'];
    }
}
