<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default SMS driver that will be used to send
    | SMS messages. This driver will be used unless another is specified
    | explicitly when sending.
    |
    */

    'default' => env('SMS_DRIVER', 'engagespark'),

    /*
    |--------------------------------------------------------------------------
    | Default Sender ID
    |--------------------------------------------------------------------------
    |
    | This is the default sender ID that will be used when sending SMS
    | messages. You can override this on a per-message basis.
    |
    */

    'default_sender_id' => env('SMS_DEFAULT_SENDER_ID', 'TXTCMDR'),

    /*
    |--------------------------------------------------------------------------
    | Available Sender IDs
    |--------------------------------------------------------------------------
    |
    | This is a comma-separated list of sender IDs that users can choose from
    | when sending SMS messages. These will populate dropdown menus.
    |
    */

    'sender_ids' => array_map('trim', explode(',', env('SMS_SENDER_IDS', 'TXTCMDR'))),

    /*
    |--------------------------------------------------------------------------
    | SMS Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the SMS drivers for your application. An example
    | configuration has been provided for the engagespark driver.
    |
    */

    'drivers' => [
        'engagespark' => [
            'api_key' => env('ENGAGESPARK_API_KEY'),
            'org_id' => env('ENGAGESPARK_ORGANIZATION_ID'),
            'sender_id' => env('ENGAGESPARK_SENDER_ID', 'TXTCMDR'),
        ],

        'null' => [
            // No configuration needed for null driver
        ],
    ],

];
