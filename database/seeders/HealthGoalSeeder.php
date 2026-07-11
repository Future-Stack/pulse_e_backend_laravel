<?php

namespace Database\Seeders;

use App\Models\HealthGoal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HealthGoalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $goals = [
            'Hormonal Balance',
            'Better Sleep',
            'Weight Management',
            'Fertility',
            'Skin',
            'Stress Reduction',
            'Athletic Performance',
            'Healthy Aging',
            'Energy Boost',
        ];

        foreach ($goals as $goal) {
            HealthGoal::updateOrCreate(
                ['title' => $goal],
            );
        }
    }
}
