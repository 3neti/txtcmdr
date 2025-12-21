<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Code Length
    |--------------------------------------------------------------------------
    |
    | The number of digits in the generated OTP code. Default is 6 digits.
    |
    */

    'digits' => 6,

    /*
    |--------------------------------------------------------------------------
    | OTP Time-To-Live (TTL)
    |--------------------------------------------------------------------------
    |
    | The number of seconds an OTP code remains valid before expiring.
    | Default is 300 seconds (5 minutes).
    |
    */

    'ttl_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Maximum Verification Attempts
    |--------------------------------------------------------------------------
    |
    | The maximum number of incorrect verification attempts allowed before
    | the OTP is locked. Default is 5 attempts.
    |
    */

    'max_attempts' => 5,

    /*
    |--------------------------------------------------------------------------
    | OTP Pepper
    |--------------------------------------------------------------------------
    |
    | A secret key used for HMAC hashing of OTP codes. This adds an extra
    | layer of security beyond the random code itself. Falls back to APP_KEY
    | if OTP_PEPPER is not set.
    |
    | WARNING: Changing this value will invalidate all active OTP verifications.
    |
    */

    'pepper' => env('OTP_PEPPER', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Send SMS
    |--------------------------------------------------------------------------
    |
    | Whether to send OTP codes via SMS. When enabled, OTP requests will
    | dispatch SendSMSJob to deliver the code to the mobile number.
    |
    */

    'send_sms' => env('OTP_SEND_SMS', true),

    /*
    |--------------------------------------------------------------------------
    | SMS Sender ID
    |--------------------------------------------------------------------------
    |
    | The default sender ID to use for OTP SMS messages. Defaults to the
    | application's default sender ID if not specified.
    |
    */

    'sender_id' => env('OTP_SENDER_ID', env('SMS_DEFAULT_SENDER_ID', 'TXTCMDR')),

    /*
    |--------------------------------------------------------------------------
    | SMS Message Template
    |--------------------------------------------------------------------------
    |
    | The message template for OTP SMS. Available variables:
    | {code} - The OTP code
    | {purpose} - The verification purpose (login, password_reset, etc.)
    | {minutes} - TTL in minutes
    | {app_name} - Application name from config
    |
    */

    'message_template' => 'Your {purpose} code is: {code}. Valid for {minutes} minutes. Do not share this code.',

];
