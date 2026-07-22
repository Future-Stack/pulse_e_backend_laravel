<?php

namespace App\Http\Controllers\Topup;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TopupProduct;
use App\Models\UserLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class TopupPaymentController extends Controller
{
    public function topupPayment(Request $request)
    {
        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            $user = $request->user();

            $topupProduct = TopupProduct::where('slug', $request->slug)
                ->where('status', 1)
                ->firstOrFail();

            // Save payment record
            $payment = Payment::create([
                'user_id' => $user->id,
                'topup_product_id' => $topupProduct->id,
                'type' => 'topup',
                'billing_cycle' => 'month',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'amount' => $topupProduct->price,
                'status' => 'pending',

            ]);

            // Create one-time PaymentIntent for top-up
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => intval($topupProduct->price * 100), // cents
                'currency' => 'usd',
                'description' => 'Topup product',
                'metadata' => [
                    'type' => 'Topup',
                    'topup_product' => $topupProduct->slug,
                    'topup_id' => $topupProduct->id,
                    'user_id' => $user->id,
                    'payment_id' => $payment->id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            //Update Payment Intent
            $payment->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Top-up payment processed successfully.',
                'data' => $payment,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Top-up payment failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process top-up payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        Log::info('Webhook Route Hit Successfully! Raw Payload: ' . $request->getContent());
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.topup_webhook_secret')
            );

            Log::info('Stripe Webhook Received: ' . $event->type);

            if ($event->type === 'payment_intent.succeeded') {
                $intent = $event->data->object;
                $paymentId = $intent->metadata->payment_id ?? null;
                $topup_id = $intent->metadata->topup_id ?? null;
                $user_id = $intent->metadata->user_id ?? null;

                Log::info("Webhook Processing - Payment ID: " . $paymentId);
                if ($paymentId) {
                    $payment = Payment::find($paymentId);

                    if ($payment && $payment->status !== 'paid') {
                        $payment->update([
                            'status' => 'paid',
                        ]);
                    }

                    $topupProduct = TopupProduct::find($topup_id);

                    $skin_scans_limit = 0;
                    $ai_coaching_limit = 0;

                    if ($topupProduct->topup_kind == 'coaching_sessions')
                    {
                        $ai_coaching_limit = $topupProduct->limit;
                    }
                    else
                    {
                        $skin_scans_limit = $topupProduct->limit;
                    }

                    // Initialize user limits
                    UserLimit::updateOrCreate(
                        ['user_id' => $user_id],
                        [
                            'payment_id' => $payment->id,
                            'skin_scans_topup_limit' => $skin_scans_limit,
                            'ai_coaching_topup_limit' => $ai_coaching_limit,
                            'topup_expires_at' => $payment->current_period_end,
                        ]
                    );
                }
            }


//            $admin = User::where('user_type', 'admin')->first();
//            if ($admin) {
//                Notification::send($admin, new AdminIconNotification([
//                    'type'      => 'inspection_booking',
//                    'title'     => 'Inspection Booking',
//                    'message'   => 'A new Inspection Booking has been paid and created.',
//                    'sender_id' => null,
//                ]));
//            }


            return response('OK', 200);


        } catch (\Exception $e) {
            Log::error('Webhook Structure/Process Error: ' . $e->getMessage());
            return response('Webhook Error: ' . $e->getMessage(), 400);
        }
    }
}
