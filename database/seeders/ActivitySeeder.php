<?php

namespace Database\Seeders;

use App\Models\Activity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activities = [
            [
                'title' => 'Sedentary',
                'description' => 'Little to no regular exercise',
            ],
            [
                'title' => 'Lightly Active',
                'description' => '1–3 days of exercise per week',
            ],
            [
                'title' => 'Moderately Active',
                'description' => '3–5 days of exercise per week',
            ],
            [
                'title' => 'Very Active',
                'description' => '6–7 days of intense training',
            ],
        ];

        foreach ($activities as $activity) {
            Activity::updateOrCreate(
                ['title' => $activity['title']],
                [
                    'description' => $activity['description'],
                    'status' => 1,
                ]
            );
        }
    }
}
