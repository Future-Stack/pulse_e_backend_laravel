<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Stripe\StripeClient;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stripeSecret = config('services.stripe.secret');



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
                'price_annual' => 125.00,
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
                'price_annual' => 219.99,
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

        //If Stripe env not found
        if (empty($stripeSecret)) {
            // Only update DB, no Stripe sync
            foreach ($plans as $plan) {
                SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
            }
            return;
        }

        //If Stripe env found
        $stripe = new StripeClient($stripeSecret);

        foreach ($plans as $plan) {
            // Skip free plan
            if ($plan['slug'] === 'free') {
                SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
                continue;
            }

            // Check if plan already exists in DB with Stripe IDs
            $existing = SubscriptionPlan::where('slug', $plan['slug'])->first();

            if ($existing && $existing->stripe_product_id) {
                // Reuse existing product/price IDs
                $productId = $existing->stripe_product_id;
                $priceMonthlyId = $existing->stripe_price_monthly_id;
                $priceAnnualId = $existing->stripe_price_annual_id;
            } else {
                // Check if product with same name exists
                $existingProducts = $stripe->products->all([
                    'active' => true,
                    'limit' => 100,
                ]);

                $productId = null;
                foreach ($existingProducts->data as $product) {
                    if (strcasecmp($product->name, $plan['name']) === 0) {
                        $productId = $product->id;
                        // Update description if needed
                        $stripe->products->update($productId, [
                            'description' => $plan['description'],
                        ]);
                        break;
                    }
                }

                if (!$productId) {
                    // Create new product if not found
                    $newProduct = $stripe->products->create([
                        'name' => $plan['name'],
                        'description' => $plan['description'],
                        'active' => true,
                    ]);
                    $productId = $newProduct->id;
                }

                // Deduplicate prices (check existing before creating)
                $existingPrices = $stripe->prices->all([
                    'product' => $productId,
                    'active' => true,
                    'limit' => 100,
                ]);

                $priceMonthlyId = null;
                $priceAnnualId = null;

                foreach ($existingPrices->data as $price) {
                    if ($price->recurring->interval === 'month' && $price->unit_amount == $plan['price_monthly'] * 100) {
                        $priceMonthlyId = $price->id;
                    }
                    if ($price->recurring->interval === 'year' && $price->unit_amount == $plan['price_annual'] * 100) {
                        $priceAnnualId = $price->id;
                    }
                }

                if (!$priceMonthlyId && $plan['price_monthly'] > 0) {
                    $newMonthly = $stripe->prices->create([
                        'unit_amount' => $plan['price_monthly'] * 100,
                        'currency' => 'usd',
                        'recurring' => ['interval' => 'month'],
                        'product' => $productId,
                    ]);
                    $priceMonthlyId = $newMonthly->id;
                }

                if (!$priceAnnualId && $plan['price_annual'] > 0) {
                    $newAnnual = $stripe->prices->create([
                        'unit_amount' => $plan['price_annual'] * 100,
                        'currency' => 'usd',
                        'recurring' => ['interval' => 'year'],
                        'product' => $productId,
                    ]);
                    $priceAnnualId = $newAnnual->id;
                }
            }

            // Save/update locally
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                array_merge($plan, [
                    'stripe_product_id' => $productId,
                    'stripe_price_monthly_id' => $priceMonthlyId,
                    'stripe_price_annual_id' => $priceAnnualId,
                ])
            );
        }


    }
}
