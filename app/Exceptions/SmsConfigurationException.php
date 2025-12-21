<?php

namespace App\Exceptions;

use Exception;

class SmsConfigurationException extends Exception
{
    /**
     * Create a new exception for missing SMS credentials
     */
    public static function missingCredentials(?int $userId = null): self
    {
        $message = $userId
            ? "User ID {$userId} does not have valid SMS credentials configured. Please configure SMS settings."
            : 'No SMS credentials configured. Please configure SMS settings or set ENGAGESPARK credentials in .env file.';

        return new self($message);
    }

    /**
     * Create a new exception for invalid SMS credentials
     */
    public static function invalidCredentials(string $details = ''): self
    {
        $message = 'SMS credentials are invalid or expired.';
        
        if ($details) {
            $message .= ' Details: '.$details;
        }

        return new self($message);
    }

    /**
     * Create a new exception for API authentication failure
     */
    public static function authenticationFailed(string $provider = 'EngageSPARK'): self
    {
        return new self(
            "Authentication failed with {$provider}. Please check your API credentials in Settings → SMS Configuration."
        );
    }
}
