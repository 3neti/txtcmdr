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
        $admin = User::firstOrCreate(
            ['email' => 'admin@disburse.cash'],
            [
                'name' => 'Lester Hurtado',
                'password' => Hash::make('password'),
            ]
        );

        // Seed SMS config from .env if credentials are present
        if (env('ENGAGESPARK_API_KEY') && env('ENGAGESPARK_ORGANIZATION_ID')) {
            UserSmsConfig::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'driver' => 'engagespark',
                ],
                [
                    'credentials' => [
                        'api_key' => env('ENGAGESPARK_API_KEY'),
                        'org_id' => env('ENGAGESPARK_ORGANIZATION_ID'),
                        'sender_id' => env('ENGAGESPARK_SENDER_ID'),
                    ],
                    'default_sender_id' => env('SMS_DEFAULT_SENDER_ID', 'TXTCMDR'),
                    'sender_ids' => array_map('trim', explode(',', env('SMS_SENDER_IDS', 'TXTCMDR'))),
                    'is_active' => true,
                ]
            );
        }
    }
}
