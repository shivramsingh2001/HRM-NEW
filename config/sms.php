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
    ],

];
