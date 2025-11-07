<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSmsConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user for testing
        $user = User::firstOrCreate(
            ['email' => 'admin@disburse.cash'],
            [
                'name' => 'Lester Hurtado',
                'password' => Hash::make('password'),
            ]
        );

        // Create SMS config from .env if credentials are present
        $apiKey = config('engagespark.api_key');
        $orgId = config('engagespark.org_id');
        $defaultSenderId = config('sms.default_sender_id');
        $senderIds = config('sms.sender_ids');

        if ($apiKey && $orgId && $defaultSenderId) {
            // Ensure sender_ids is an array
            if (is_string($senderIds)) {
                $senderIds = array_map('trim', explode(',', $senderIds));
            } elseif (! is_array($senderIds)) {
                $senderIds = [$defaultSenderId];
            }

            UserSmsConfig::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'driver' => 'engagespark',
                ],
                [
                    'credentials' => [
                        'api_key' => $apiKey,
                        'org_id' => $orgId,
                    ],
                    'default_sender_id' => $defaultSenderId,
                    'sender_ids' => $senderIds,
                    'is_active' => true,
                ]
            );

            $this->command->info('✓ Created admin user with SMS config from .env');
        } else {
            $this->command->info('✓ Created admin user (no SMS config - set via Settings → SMS)');
        }
    }
}
