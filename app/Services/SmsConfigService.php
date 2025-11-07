<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSmsConfig;

class SmsConfigService
{
    /**
     * Get SMS configuration with user override → app config fallback (CLI only)
     *
     * For web requests, users MUST have their own SMS config.
     * Fallback to .env only applies for console/artisan commands.
     */
    public function getConfigForUser(?User $user = null, ?string $driver = null): array
    {
        $user = $user ?? auth()->user();
        $driver = $driver ?? config('sms.default', 'engagespark');

        // Try user config first
        if ($user) {
            $smsConfig = UserSmsConfig::where('user_id', $user->id)
                ->where('driver', $driver)
                ->where('is_active', true)
                ->first();

            if ($smsConfig && $smsConfig->hasRequiredCredentials()) {
                return [
                    'driver' => $driver,
                    'credentials' => $smsConfig->credentials,
                    'default_sender_id' => $smsConfig->default_sender_id ?? config('sms.default_sender_id'),
                    'sender_ids' => $smsConfig->sender_ids ?? config('sms.sender_ids'),
                    'source' => 'user',
                ];
            }
        }

        // Fallback to app config ONLY for console/artisan commands
        // Web users MUST configure their own SMS settings
        if (app()->runningInConsole()) {
            return [
                'driver' => $driver,
                'credentials' => config("sms.drivers.{$driver}", []),
                'default_sender_id' => config('sms.default_sender_id'),
                'sender_ids' => config('sms.sender_ids'),
                'source' => 'app',
            ];
        }

        // No config available for web user
        return [
            'driver' => $driver,
            'credentials' => [],
            'default_sender_id' => null,
            'sender_ids' => [],
            'source' => 'none',
        ];
    }

    /**
     * Get EngageSPARK credentials specifically (helper for current implementation)
     */
    public function getEngageSparkConfig(?User $user = null): array
    {
        $config = $this->getConfigForUser($user, 'engagespark');

        return [
            'api_key' => $config['credentials']['api_key'] ?? null,
            'org_id' => $config['credentials']['org_id'] ?? null,
            'sender_id' => $config['credentials']['sender_id'] ?? $config['default_sender_id'] ?? null,
            'default_sender_id' => $config['default_sender_id'],
            'sender_ids' => $config['sender_ids'],
            'source' => $config['source'],
        ];
    }
}
