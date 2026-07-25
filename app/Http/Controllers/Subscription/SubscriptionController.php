<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserLimit;
use App\Notifications\AdminIconNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class SubscriptionController extends Controller
{
    public function createSubscription(Request $request)
    {
        try {
            $user = auth()->user();

            if ($user->onboardingCompleted == 0)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Please Complete Your Onboarding first'
                ]);
            }

            $request->validate([
                'plan_slug' => 'required|exists:subscription_plans,slug',
                'billing_cycle' => 'required|in:month,year',
                'payment_method_id' => 'required|string', // Stripe PaymentMethod ID
            ]);

            DB::beginTransaction();

            $plan = SubscriptionPlan::where('slug', $request->plan_slug)->first();

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Create or retrieve Stripe customer
            $stripeCustomer = $user->stripe_customer_id ?? \Stripe\Customer::create([
                'email' => $user->email,
                'name' => $user->full_name,
            ])->id;

            $user->update(['stripe_customer_id' => $stripeCustomer]);

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));


            //Attach Payment Methods to customer
            $stripe->paymentMethods->attach(
                $request->payment_method_id,
                ['customer' => $stripeCustomer]
            );

            $stripe->customers->update($stripeCustomer, [
                'invoice_settings' => [
                    'default_payment_method' => $request->payment_method_id,
                ],
            ]);

            // Create subscription in Stripe
            $subscription = $stripe->subscriptions->create([
                'customer' => $stripeCustomer,
                'items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product' => $plan->stripe_product_id, // ⚠️ Ensure this matches a valid Stripe product ID
                        'unit_amount' => $request->billing_cycle === 'month'
                            ? $plan->price_monthly * 100
                            : $plan->price_annual * 100,
                        'recurring' => ['interval' => $request->billing_cycle],
                    ],
                ]],
                'default_payment_method' => $request->payment_method_id,
            ]);


            // Record payment
            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'type' => 'subscription',
                'billing_cycle' => $request->billing_cycle,
                'current_period_start' => now(),
                'current_period_end' => $request->billing_cycle === 'month'
                    ? now()->addMonth()
                    : now()->addYear(),
                'stripe_subscription_id' => $subscription->id,
                'amount' => $request->billing_cycle === 'month'
                    ? $plan->price_monthly
                    : $plan->price_annual,
                'status' => 'paid',
            ]);

            // Initialize user limits
            UserLimit::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'subscription',
                ],
                [
                    'payment_id' => $payment->id,
                    'skin_scans_limit' => $plan->skin_scans_limit,
                    'ai_coaching_limit' => $plan->ai_coaching_limit,
                    'deep_reports_limit' => $plan->deep_reports_limit,
                    'subscription_expires_at' => $payment->current_period_end,
                ]
            );

            $admin = User::where('user_type', 'admin')->first();
            if ($admin) {
                Notification::send($admin, new AdminIconNotification([
                    'type' => 'subscription',
                    'title' => 'New Subscription',
                    'message' => 'A new subscription has been created.',
                    'sender_id' => null,
                ]));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully.',
                'data' => $subscription,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelSubscription(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'subscription_id' => 'required|string',
        ]);

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        $stripe->subscriptions->cancel($request->subscription_id);

        Payment::where('stripe_subscription_id', $request->subscription_id)
            ->update(['status' => 'cancel']);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully.',
        ]);
    }

    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.subscription_webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $subscriptionId = $event->data->object->subscription;
                Payment::where('stripe_subscription_id', $subscriptionId)
                    ->update(['status' => 'paid']);
                break;

            case 'invoice.payment_failed':
                $subscriptionId = $event->data->object->subscription;
                Payment::where('stripe_subscription_id', $subscriptionId)
                    ->update(['status' => 'pending']);
                break;

            case 'customer.subscription.deleted':
                $subscriptionId = $event->data->object->id;
                Payment::where('stripe_subscription_id', $subscriptionId)
                    ->update(['status' => 'cancel']);
                break;

            case 'customer.subscription.updated':
                $subscription = $event->data->object;
                Payment::where('stripe_subscription_id', $subscription->id)
                    ->update([
                        'current_period_end' => Carbon::createFromTimestamp($subscription->current_period_end),
                        'status' => $subscription->status === 'active' ? 'paid' : 'pending',
                    ]);
                break;

            case 'customer.subscription.created':
                Log::info('New subscription created: ' . $event->data->object->id);
                break;
        }

        //Future Events
        //customer.subscription.updated
        //customer.subscription.created
        //invoice.upcoming
        //payment_method.attached

        return response()->json(['success' => true]);
    }
    // Create a PaymentMethod (test card for dev)
//            $paymentMethod = $stripe->paymentMethods->create([
//                'type' => 'card',
//                'card' => [
//                    'number' => '4242424242424242', // Stripe test Visa
//                    'exp_month' => 12,
//                    'exp_year' => 2026,
//                    'cvc' => '123',
//                ],
//            ]);


//revenue breakdown

    public function revenueBreakdown(): JsonResponse
    {
        $plans = SubscriptionPlan::withCount([
            'payments as subscribers' => function ($query) {
                $query->where('type', 'subscription')
                    ->where('status', 'paid');
            }
        ])
            ->get();

        $data = $plans->map(function ($plan) {

            $monthlyRevenue = $plan->subscribers * $plan->price_monthly;
            $annualRevenue = $plan->subscribers * $plan->price_annual;

            return [
                'plan' => $plan->name,
                'subscribers' => $plan->subscribers,

                'monthly_revenue' => $plan->price_monthly > 0
                    ? '$' . number_format($monthlyRevenue, 2)
                    : '-',

                'annual_revenue' => $plan->price_annual > 0
                    ? '$' . number_format($annualRevenue, 2)
                    : '-',
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Revenue breakdown retrieved successfully.',
            'data' => $data,
        ]);
    }

    public function checkSubscriptionUser()
    {
        try {
            $user = auth()->user();

            $plan = $user->latestSubscription->subscriptionPlan ?? null;
            $limit = $user->userLimits;

            return response()->json([
                'success' => true,
                'message' => 'User Subscription retrieved successfully.',
                'plan' => $plan,
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            Log::error('Check Subscription:' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plan from user',
                'error' => $e->getMessage(),
            ], 500);
        }


    }
}
