<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration Requirement During Registration
    |--------------------------------------------------------------------------
    |
    | This option controls whether SMS configuration is required, optional,
    | or disabled during user registration.
    |
    | Supported: "optional", "required", "disabled"
    |
    | - optional: Users can skip SMS config and use application defaults
    | - required: Users must provide their own SMS credentials to register
    | - disabled: SMS configuration step is completely hidden
    |
    */

    'sms_config' => env('REGISTRATION_SMS_CONFIG', 'optional'),
];
