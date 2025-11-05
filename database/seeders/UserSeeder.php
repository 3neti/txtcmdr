<?php

namespace Database\Seeders;

use App\Models\User;
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
        User::firstOrCreate(
            ['email' => 'admin@disburse.cash'],
            [
                'name' => 'Lester Hurtado',
                'password' => Hash::make('password'),
            ]
        );
    }
}
