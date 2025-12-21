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

];
