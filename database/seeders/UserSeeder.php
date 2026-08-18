<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::updateOrCreate(

            ['email' => 'info@fightthenumber.com'],
            [
                'full_name' => 'Super Admin',

                'password'   => Hash::make('Password@123'),
                'status'     => 'active',
                'user_type'  => 'admin',
                'is_marketplace_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $user = User::updateOrCreate(
            ['email' => 'user@gmail.com'],
            [
                'full_name' => 'User',

                'password'   => Hash::make('Password@123'),
                'status'     => 'active',
                'user_type'  => 'user',
                'email_verified_at' => now(),
            ]
        );


    }
}
