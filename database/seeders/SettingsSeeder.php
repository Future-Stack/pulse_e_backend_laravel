<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            Setting::updateOrCreate(
                ['id' => 1], // singleton row
                [
                    'platform_name'             => 'NEUMERA',
                    'email'              => 'support@neumera.com',

                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to seed settings table: ' . $e->getMessage());
        }
    }
}
