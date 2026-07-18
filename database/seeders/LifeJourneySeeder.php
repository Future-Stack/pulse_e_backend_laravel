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
                'subtitle' => 'Connect inner health with visible outcomes',
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
                'title' => 'Cycle Tracking',
                'subtitle' => 'Build hormonal baseline & fertility awareness',
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
                'title' => 'Athlete Performance',
                'subtitle' => 'Optimize training around female physiology',
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
                'icon' => 'pregnancy.png',
                'title' => 'Pregnancy',
                'subtitle' => 'Support maternal wellness throughout',
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
                'icon' => 'postpartum.png',
                'title' => 'Postpartum Recovery',
                'subtitle' => 'Support recovery after childbirth',
                'description' => 'Energy levels are improving week-over-week. The fatigue you\'re still feeling is largely from sleep fragmentation — hormones are still recalibrating. Expect a significant improvement around week 8-10. Book your 6-week checkup if you haven\'t yet.',
                'features' => [
                    'Recovery Tracking',
                    'Sleep Disturbance Monitoring',
                    'Energy Monitoring',
                    'Sleep Monitoring',
                    'Mood Check-ins',
                    'Wellness Milestones'
                ]
            ],
            [
                'icon' => 'menopause.png',
                'title' => 'Perimenopause / Menopause',
                'subtitle' => 'Navigate hormonal transition with clarity',
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
                'icon' => 'lifelong.png',
                'title' => 'Lifelong Thriving',
                'subtitle' => 'Support long-term health optimization',
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