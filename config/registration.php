<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration Requirement During Registration
    |--------------------------------------------------------------------------
    |
    | SMS configuration is REQUIRED during registration (Option A architecture).
    | Users must provide their own EngageSPARK credentials to use the application.
    |
    | This ensures proper multi-tenancy and each user uses their own SMS account.
    |
    */

    'sms_config' => 'required', // Hardcoded for Option A architecture
];
