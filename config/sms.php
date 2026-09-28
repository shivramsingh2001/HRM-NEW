<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Airtel IQ SMS gateway (OTP delivery)
    |--------------------------------------------------------------------------
    */
    'airtel' => [
        'username' => env('AIRTEL_USERNAME'),
        'password' => env('AIRTEL_PASSWORD'),
        'customer_id' => env('CUSTOMER_ID'),
        'dlt_template_id' => env('DLT_TEMPLATE_ID'),
        'entity_id' => env('ENTITY_ID'),
        'message_type' => env('MESSAGE_TYPE'),
        'source_address' => env('SOURCE_ADDRESS'),

        // Broadcast Notification module (Phase D) — SEPARATE from
        // dlt_template_id above, which is the OTP-login template. Indian
        // DLT rules require every SMS to match a pre-registered, approved
        // template; free-text admin-authored broadcast bodies CANNOT be
        // sent through this gateway until a "broadcast notification"
        // template is registered with Airtel/DLT and its id set here. Left
        // unset by default — App\Channels\SmsChannel gracefully no-ops
        // (logs and skips, never throws) until this is configured, the same
        // degrade-gracefully pattern App\Services\FirebaseService::isReady()
        // already uses for missing FCM credentials.
        'broadcast_dlt_template_id' => env('DLT_TEMPLATE_ID_BROADCAST'),
    ],

];
