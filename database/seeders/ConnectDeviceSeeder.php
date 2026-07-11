<?php

namespace Database\Seeders;

use App\Models\ConnectDevice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConnectDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $baseUrl = url('/backend/devices');

        $devices = [
            [
                'icon' => 'apple.png',
                'title' => 'Apple HealthKit',
                'description' => 'iOS health & activity data',
            ],
            [
                'icon' => 'android.png',
                'title' => 'Android Health Connect',
                'description' => 'Android health & activity data',
            ],
            [
                'icon' => 'fitbit.png',
                'title' => 'Fitbit',
                'description' => 'Wearable activity, sleep, HR',
            ],
            [
                'icon' => 'fitbit.png',
                'title' => 'MyFitnessPal',
                'description' => 'Wearable activity, sleep, HR',
            ],
            [
                'icon' => 'fitbit.png',
                'title' => 'Terra API',
                'description' => 'Unified wearable aggregation (Oura, Whoop, Garmin & more)',
            ],
        ];

        foreach ($devices as $device) {
            ConnectDevice::updateOrCreate(
                ['title' => $device['title']],
                [
                    'icon' => $baseUrl . '/'. $device['icon'],
                    'description' => $device['description'],
                    'status' => 1,
                ]
            );

        }
    }
}
