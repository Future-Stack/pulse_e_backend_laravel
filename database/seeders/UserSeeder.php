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

            ['email' => 'admin@gmail.com'],
            [
                'first_name' => 'Super',
                'last_name'  => 'Admin',
                'password'   => Hash::make('Password@123'),
                'status'     => 'active',
                'user_type'  => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $user = User::updateOrCreate(
            ['email' => 'user@gmail.com'],
            [
                'first_name' => 'John',
                'last_name'  => 'Carter',
                'password'   => Hash::make('Password@123'),
                'status'     => 'active',
                'user_type'  => 'user',
                'email_verified_at' => now(),
            ]
        );


    }
}
