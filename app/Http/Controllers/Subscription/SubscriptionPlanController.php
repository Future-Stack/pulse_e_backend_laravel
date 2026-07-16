<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionPlanController extends Controller
{
//    public function createOrUpdate(Request $request, string $slug)
//    {
//        try {
//            $validated = $request->validate([
//                'name'                     => 'required|string|max:255',
//                'description'              => 'nullable|string',
//                'price_monthly'            => 'required|numeric|min:0',
//                'price_annual'             => 'nullable|numeric|min:0',
//                'skin_scans_limit'         => 'required|integer|min:-1',
//                'ai_coaching_limit'        => 'required|integer|min:-1',
//                'ai_coaching_model'        => 'nullable|string|max:50',
//                'deep_reports_limit'       => 'required|integer|min:-1',
//                'daily_summary_frequency'  => 'required|in:weekly,unlimited',
//                'tracking_label'           => 'required|string|max:255',
//                'tracking_integrations'    => 'nullable|array',
//                'status'                   => 'boolean',
//            ]);
//
//            DB::beginTransaction();
//
//            $validated['slug'] = $slug;
//
//            $plan = SubscriptionPlan::updateOrCreate(
//                ['slug' => $validated['slug']],
//                [
//                    'name'                    => $validated['name'],
//                    'description'             => $validated['description'] ?? null,
//                    'price_monthly'           => $validated['price_monthly'],
//                    'price_annual'            => $validated['price_annual'] ?? null,
//                    'skin_scans_limit'        => $validated['skin_scans_limit'],
//                    'ai_coaching_limit'       => $validated['ai_coaching_limit'],
//                    'ai_coaching_model'       => $validated['ai_coaching_model'] ?? null,
//                    'deep_reports_limit'      => $validated['deep_reports_limit'],
//                    'daily_summary_frequency' => $validated['daily_summary_frequency'],
//                    'tracking_label'          => $validated['tracking_label'],
//                    'tracking_integrations'   => $validated['tracking_integrations'] ?? null,
//                    'status'                  => $validated['status'] ?? true,
//                ]
//            );
//
//            DB::commit();
//
//            return response()->json([
//                'success' => true,
//                'message' => 'Subscription plan saved successfully.',
//                'data'    => $plan,
//            ], 200);
//
//        } catch (\Throwable $e) {
//            DB::rollBack();
//            Log::error('Subscription plan create/update failed: '.$e->getMessage());
//
//            return response()->json([
//                'success' => false,
//                'message' => 'Failed to save subscription plan.',
//                'error'   => $e->getMessage(),
//            ], 500);
//        }
//    }

    public function createOrUpdate(Request $request, string $slug)
    {
        try {
            $validated = $request->validate([
                'name'                     => 'required|string|max:255',
                'description'              => 'nullable|string',
                'price_monthly'            => 'required|numeric|min:0',
                'price_annual'             => 'nullable|numeric|min:0',
                'skin_scans_limit'         => 'required|integer|min:-1',
                'ai_coaching_limit'        => 'required|integer|min:-1',
                'ai_coaching_model'        => 'nullable|string|max:50',
                'deep_reports_limit'       => 'required|integer|min:-1',
                'daily_summary_frequency'  => 'required|in:weekly,unlimited',
                'tracking_label'           => 'required|string|max:255',
                'tracking_integrations'    => 'nullable|array',
                'status'                   => 'boolean',
            ]);

            DB::beginTransaction();

            $validated['slug'] = $slug;

            $plan = SubscriptionPlan::updateOrCreate(
                ['slug' => $validated['slug']],
                $validated
            );

            // 🔹 Stripe sync only if configured and not free plan
            $stripeSecret = config('services.stripe.secret');
            if (!empty($stripeSecret) && $plan->slug !== 'free') {
                $stripe = new \Stripe\StripeClient($stripeSecret);

                // Update or create product
                if ($plan->stripe_product_id) {
                    $stripe->products->update($plan->stripe_product_id, [
                        'name' => $plan->name,
                        'description' => $plan->description,
                    ]);
                } else {
                    $product = $stripe->products->create([
                        'name' => $plan->name,
                        'description' => $plan->description,
                        'active' => true,
                    ]);
                    $plan->stripe_product_id = $product->id;
                }

                // Prices are immutable → create new if amounts change
                if ($plan->price_monthly > $request->price_monthly) {
                    $priceMonthly = $stripe->prices->create([
                        'unit_amount' => $plan->price_monthly * 100,
                        'currency' => 'usd',
                        'recurring' => ['interval' => 'month'],
                        'product' => $plan->stripe_product_id,
                    ]);
                    $plan->stripe_price_monthly_id = $priceMonthly->id;
                }

                if ($plan->price_annual > 0) {
                    $priceAnnual = $stripe->prices->create([
                        'unit_amount' => $plan->price_annual * 100,
                        'currency' => 'usd',
                        'recurring' => ['interval' => 'year'],
                        'product' => $plan->stripe_product_id,
                    ]);
                    $plan->stripe_price_annual_id = $priceAnnual->id;
                }

                $plan->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan saved successfully.',
                'data'    => $plan,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Subscription plan create/update failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save subscription plan.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllPlans()
    {
        try {
            $plans = SubscriptionPlan::where('status', true)
                ->get()
                ->map(function ($plan) {
                    return [
                        'id'                      => $plan->id,
                        'slug'                    => $plan->slug,
                        'name'                    => $plan->name,
                        'description'             => $plan->description,
                        'price_monthly'           => $plan->price_monthly,
                        'price_annual'            => $plan->price_annual,
                        'skin_scans_limit'        => $plan->skin_scans_limit,
                        'ai_coaching_limit'       => $plan->ai_coaching_limit,
                        'ai_coaching_model'       => $plan->ai_coaching_model,
                        'deep_reports_limit'      => $plan->deep_reports_limit,
                        'daily_summary_frequency' => ucfirst($plan->daily_summary_frequency),
                        'tracking_label'          => $plan->tracking_label,
                        'tracking_integrations'   => json_decode($plan->tracking_integrations, true),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Subscription plans fetched successfully.',
                'data'    => $plans,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Fetch subscription plans failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription plans.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getPlanBySlug($slug)
    {
        try {
            $plan = SubscriptionPlan::where('slug', $slug)
                ->where('status', true)
                ->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription plan not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan fetched successfully.',
                'data'    => [
                    'id'                      => $plan->id,
                    'slug'                    => $plan->slug,
                    'name'                    => $plan->name,
                    'description'             => $plan->description,
                    'price_monthly'           => $plan->price_monthly,
                    'price_annual'            => $plan->price_annual,
                    'skin_scans_limit'        => $plan->skin_scans_limit,
                    'ai_coaching_limit'       => $plan->ai_coaching_limit,
                    'ai_coaching_model'       => $plan->ai_coaching_model,
                    'deep_reports_limit'      => $plan->deep_reports_limit,
                    'daily_summary_frequency' => ucfirst($plan->daily_summary_frequency),
                    'tracking_label'          => $plan->tracking_label,
                    'tracking_integrations'   => json_decode($plan->tracking_integrations, true),
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Fetch subscription plan failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription plan.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
