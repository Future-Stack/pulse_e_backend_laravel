<?php

namespace Database\Seeders;

use App\Models\LifeJourney;
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
                'subtitle' => 'Skin, hair, confidence - the entry wedge.',
                'description' => 'Revealing how skin, energy, and outward vitality reflect inner health, turning daily signals into visible results.',
                'features' => [
                    'Skin Health Tracking',
                    'Hydration Monitoring',
                    'Energy & Vitality Scores',
                    'Sleep Impact Analysis',
                    'Skin Trend Monitoring'
                ]
            ],
            [
                'icon' => 'tracking.png',
                'title' => 'Cycle & Fertility',
                'subtitle' => 'Cycle, ovulation, conception planning.',
                'description' => 'Making sense of your cycle month to month — so ovulation, hormones, and fertile windows stop being a mystery, whether you\'re planning for pregnancy or just getting to know your body.',
                'features' => [
                    'Cycle Tracking',
                    'Ovulation Prediction',
                    'Fertility Window Detection',
                    'Hormone Pattern Analysis',
                    'Cycle Forecasting'
                ]
            ],
            [
                'icon' => 'athlete.png',
                'title' => 'Athlete',
                'subtitle' => 'Training, recovery, performance by hormone phase.',
                'description' => 'Optimizing training, recovery, and performance around the hormonal rhythms that female-specific data too often ignores.',
                'features' => [
                    'Training Load Monitoring',
                    'Recovery Analysis',
                    'HRV Tracking',
                    'Cycle-Based Training Plans',
                    'Fatigue Detection'
                ]
            ],
            [
                'icon' => 'menopause.png',
                'title' => 'Perimenopause/Menopause & Vitality',
                'subtitle' => 'Symptom navigation and long-term vitality.',
                'description' => 'Turning the hormonal upheaval of perimenopause and menopause into something you can finally understand — easing symptoms today while protecting your strength for the years ahead.',
                'features' => [
                    'Hot Flash Tracking',
                    'Sleep Disturbance Monitoring',
                    'Mood Changes',
                    'Hormonal Trend Analysis',
                    'Menopause Education'
                ]
            ],
            [
                'icon' => 'pregnancy.png',
                'title' => 'Pregnancy & Postpartum',
                'subtitle' => 'Prenatal through recovery, supported.',
                'description' => 'Tracking the body\'s rapid changes and supporting recovery through one of life\'s most demanding chapters.',
                'features' => [
                    'Week Tracking',
                    'Symptom Monitoring',
                    'Health Checkpoints',
                    'Sleep Tracking',
                    'Nutrition Reminders'
                ]
            ],
            [
                'icon' => 'lifelong.png',
                'title' => 'Lifelong Thriving',
                'subtitle' => 'Prevention and healthspan for the long run.',
                'description' => 'Sustaining strength, clarity, and well-being across the years, with intelligence that keeps adapting as you do.',
                'features' => [
                    'Healthy Aging Insights',
                    'Cognitive Wellness Monitoring',
                    'Vitality Scoring',
                    'Mobility Trends',
                    'Preventive Health Signals'
                ]
            ],
        ];

        foreach ($journeys as $journey) {
            $insertedJourney = LifeJourney::updateOrCreate(
                ['title' => $journey['title']],
                [
                    'icon' => $baseUrl . '/' . $journey['icon'],
                    'subtitle' => $journey['subtitle'],
                    'description' => $journey['description'],
                    'status' => 1,
                ]
            );

            $insertedJourney->features()->delete();

            foreach ($journey['features'] as $featureName) {
                $insertedJourney->features()->create([
                    'feature_name' => $featureName,
                ]);
            }
        }
    }
}