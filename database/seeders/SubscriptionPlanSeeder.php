<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Basic access with limited features.',
                'price_monthly' => 0,
                'price_annual' => 0,
                'skin_scans_limit' => 2,
                'ai_coaching_limit' => 5,
                'ai_coaching_model' => 'haiku',
                'deep_reports_limit' => 0,
                'daily_summary_frequency' => 'weekly',
                'tracking_label' => 'Cycle + BBT, manual + limited sync',
                'tracking_integrations' => json_encode(['cycle_tracking', 'bbt_manual']),
                'status' => true,
            ],
            [
                'slug' => 'premium',
                'name' => 'Premium',
                'description' => 'Full multi-stage access with wearables and lab OCR.',
                'price_monthly' => 14.99,
                'price_annual' => 149.99,
                'skin_scans_limit' => 6,
                'ai_coaching_limit' => 75,
                'ai_coaching_model' => 'all',
                'deep_reports_limit' => 0,
                'daily_summary_frequency' => 'unlimited',
                'tracking_label' => 'Full multi-stage, all wearables, lab OCR',
                'tracking_integrations' => json_encode(['wearables', 'lab_ocr']),
                'status' => true,
            ],
            [
                'slug' => 'elite',
                'name' => 'Elite',
                'description' => 'Everything in Premium plus priority routing and deep reports.',
                'price_monthly' => 24.99,
                'price_annual' => 249.99,
                'skin_scans_limit' => 12,
                'ai_coaching_limit' => 150,
                'ai_coaching_model' => 'all',
                'deep_reports_limit' => 3,
                'daily_summary_frequency' => 'unlimited',
                'tracking_label' => 'Everything in Premium + priority routing',
                'tracking_integrations' => json_encode(['wearables', 'lab_ocr', 'priority_routing']),
                'status' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
