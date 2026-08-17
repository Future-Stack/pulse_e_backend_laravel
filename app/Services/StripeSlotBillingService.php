<?php

namespace App\Services;

use App\Models\SponsoredSlot;
use Illuminate\Support\Facades\Log;

/**
 * Handles B2B Stripe billing workflows for sponsored slots.
 * Manages checkout session generation and processes webhook lifecycle events
 * (payment success/failure) to transition slot statuses.
 */
class StripeSlotBillingService
{
    /**
     * Generates a checkout link/session metadata for a sponsored slot.
     */
    public function createCheckoutSession(SponsoredSlot $slot, string $successUrl, string $cancelUrl): array
    {
        $amountCents = $slot->monthly_rate_cents ?? 15000; // default rate if not explicitly set

        return [
            'slot_id' => $slot->id,
            'amount_cents' => $amountCents,
            'currency' => 'usd',
            'checkout_url' => "https://checkout.stripe.com/pay/cs_test_mock_session_{$slot->id}",
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'status' => 'pending_payment',
        ];
    }

    /**
     * Handles webhook payload for payment completion or failure.
     */
    public function handleWebhook(array $event): bool
    {
        $eventType = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        Log::info("Processing Stripe webhook for marketplace: {$eventType}");

        switch ($eventType) {
            case 'checkout.session.completed':
            case 'invoice.payment_succeeded':
                return $this->handlePaymentSuccess($data);

            case 'invoice.payment_failed':
            case 'customer.subscription.deleted':
                return $this->handlePaymentFailure($data);

            default:
                return false;
        }
    }

    private function handlePaymentSuccess(array $object): bool
    {
        $slotId = $object['metadata']['slot_id'] ?? $object['client_reference_id'] ?? null;

        if (! $slotId) {
            return false;
        }

        $slot = SponsoredSlot::find($slotId);

        if (! $slot) {
            return false;
        }

        // Verify provider is still active before activating slot (sponsored integrity rule)
        if ($slot->provider && $slot->provider->status === 'active') {
            $slot->update(['status' => 'active']);
            return true;
        }

        return false;
    }

    private function handlePaymentFailure(array $object): bool
    {
        $slotId = $object['metadata']['slot_id'] ?? $object['client_reference_id'] ?? null;

        if (! $slotId) {
            return false;
        }

        $slot = SponsoredSlot::find($slotId);

        if (! $slot) {
            return false;
        }

        $slot->update(['status' => 'cancelled']);

        return true;
    }
}
