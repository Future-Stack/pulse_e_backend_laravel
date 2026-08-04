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
                'description' => 'Recent sleep disruption may be contributing to increased skin redness. A consistent 10pm bedtime could restore your glow within 5-7 days.',
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
                'description' => "You're in your Ovulatory Phase (Day 14). Peak fertility window opens in the next 24-48 hours. Estrogen is peaking — expect elevated energy and confidence.",
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
                'description' => 'Recovery metrics suggest reducing training intensity today. Your HRV indicates moderate nervous system fatigue — a light session will serve you better.',
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
                'title' => 'Peri / Menopause & Vitality',
                'subtitle' => 'Symptom navigation and long-term vitality.',
                'description' => 'Hot flash frequency is down 20% this week — a positive trend. Evening flashes correlate strongly with high-stress days. A consistent 9pm wind-down routine could reduce your overnight flash count within 5-7 days.',
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
                'description' => 'Week 24 — your baby is the size of a corn cob! Focus on sleep positioning (left side preferred) and stay well hydrated.',
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
                'description' => 'Your long-term vitality score has improved 8 points over 6 weeks — sustained sleep quality is the primary driver. Consistent HRV improvement (+12ms) signals cardiovascular adaptation. Your bone density scan is overdue — the preventive window is now.',
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
                    'feature_name' => $featureName
                ]);
            }
        }
    }
}
