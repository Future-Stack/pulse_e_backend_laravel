<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\UserLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function createSubscription(Request $request)
    {
        try {
            $user = auth()->user();

            $request->validate([
                'plan_slug' => 'required|exists:subscription_plans,slug',
                'billing_cycle' => 'required|in:monthly,annual',
                'payment_method_id' => 'required|string', // Stripe PaymentMethod ID
            ]);

            $plan = SubscriptionPlan::where('slug', $request->plan_slug)->first();

            // Create or retrieve Stripe customer
            $stripeCustomer = $user->stripe_customer_id ?? \Stripe\Customer::create([
                'email' => $user->email,
                'name'  => $user->full_name,
            ])->id;

            $user->update(['stripe_customer_id' => $stripeCustomer]);

            // Create subscription in Stripe
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $subscription = $stripe->subscriptions->create([
                'customer' => $stripeCustomer,
                'items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product'  => $plan->slug, // ⚠️ Ensure this matches a valid Stripe product ID
                        'unit_amount' => $request->billing_cycle === 'monthly'
                            ? $plan->price_monthly * 100
                            : $plan->price_annual * 100,
                        'recurring' => ['interval' => $request->billing_cycle],
                    ],
                ]],
                'default_payment_method' => $request->payment_method_id,
            ]);

            DB::beginTransaction();

            // Record payment
            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'type' => 'subscription',
                'billing_cycle' => $request->billing_cycle,
                'current_period_start' => now(),
                'current_period_end' => $request->billing_cycle === 'monthly'
                    ? now()->addMonth()
                    : now()->addYear(),
                'stripe_subscription_id' => $subscription->id,
                'amount' => $request->billing_cycle === 'monthly'
                    ? $plan->price_monthly
                    : $plan->price_annual,
                'status' => 'pending',
            ]);

            // Initialize user limits
            UserLimit::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'payment_id' => $payment->id,
                    'skin_scans_limit' => $plan->skin_scans_limit,
                    'ai_coaching_limit' => $plan->ai_coaching_limit,
                    'deep_reports_limit' => $plan->deep_reports_limit,
                    'subscription_expires_at' => $payment->current_period_end,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully.',
                'data' => $subscription,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription.',
                'error'   => $e->getMessage(),
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
        }

        return response()->json(['success' => true]);
    }


}
