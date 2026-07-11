<?php

namespace Database\Seeders;

use App\Models\LifeJourney;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LifeJourneySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $baseUrl = url('/backend/journeys');

        $journeys = [
            [
                'icon' => 'beauty.png',
                'title' => 'Beauty & Radiance',
                'description' => 'Connect inner health with visible outcomes',
            ],
            [
                'icon' => 'tracking.png',
                'title' => 'Cycle Tracking',
                'description' => 'Build hormonal baseline & fertility awareness',
            ],
            [
                'icon' => 'athlete.png',
                'title' => 'Athlete Performance',
                'description' => 'Optimize training around female physiology',
            ],
            [
                'icon' => 'pregnancy.png',
                'title' => 'Pregnancy',
                'description' => 'Support maternal wellness throughout',
            ],
            [
                'icon' => 'postpartum.png',
                'title' => 'Postpartum Recovery',
                'description' => 'Support recovery after childbirth',
            ],
            [
                'icon' => 'menopause.png',
                'title' => 'Perimenopause / Menopause',
                'description' => 'Navigate hormonal transition with clarity',
            ],
            [
                'icon' => 'lifelong.png',
                'title' => 'Lifelong Thriving',
                'description' => 'Support long-term health optimization',
            ],
        ];

        foreach ($journeys as $journey) {
            LifeJourney::updateOrCreate(
                ['title' => $journey['title']],
                [
                    'icon' => $baseUrl . '/' . $journey['icon'],
                    'description' => $journey['description'],
                    'status' => 1,
                ]
            );
        }
    }
}
