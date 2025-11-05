<?php

use LBHurtado\EngageSpark\EngageSparkChannel;

return [
    'api_key' => env('ENGAGESPARK_API_KEY'),
    'org_id' => env('ENGAGESPARK_ORGANIZATION_ID'),
    'sender_id' => env('ENGAGESPARK_SENDER_ID', 'serbis.io'),
    'end_points' => [
        'sms' => env('ENGAGESPARK_SMS_ENDPOINT', 'https://api.engagespark.com/v1/sms/contact'),
        'topup' => env('ENGAGESPARK_AIRTIME_ENDPOINT', 'https://api.engagespark.com/v1/airtime-topup'),
    ],
    'notification' => [
        'channels' => array_merge(['database'], env('SEND_NOTIFICATION', false)
            ? [env('NOTIFICATION_CLASS', EngageSparkChannel::class)]
            : []),
    ],
    'topup' => [
        'minimum' => env('ENGAGESPARK_MIN_TOPUP', 15),
        'maximum' => env('ENGAGESPARK_MAX_TOPUP', 1000),
    ],
    'notifiable' => [
        'route' => env('ENGAGESPARK_NOTIFIABLE_ROUTE', 'mobile')
    ]
];
