<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            PagesSeeder::class,
            UserSeeder::class,
            ActivitySeeder::class,
            ConnectDeviceSeeder::class,
            HealthGoalSeeder::class,
            LifeJourneySeeder::class,
            LifeStageSeeder::class,
        ]);
    }
}
