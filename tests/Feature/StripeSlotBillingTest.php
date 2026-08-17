<?php

namespace Tests\Feature;

use App\Models\Metro;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Models\SponsoredSlot;
use App\Services\StripeSlotBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeSlotBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_checkout_session_returns_valid_structure(): void
    {
        $metro = Metro::factory()->create();
        $category = ProviderCategory::factory()->create();
        $provider = Provider::factory()->create(['status' => 'active']);

        $slot = SponsoredSlot::create([
            'metro_id' => $metro->id,
            'category_id' => $category->id,
            'slot_number' => 1,
            'provider_id' => $provider->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'reserved',
            'monthly_rate_cents' => 20000,
        ]);

        $service = new StripeSlotBillingService();
        $checkout = $service->createCheckoutSession($slot, 'https://example.com/success', 'https://example.com/cancel');

        $this->assertEquals($slot->id, $checkout['slot_id']);
        $this->assertEquals(20000, $checkout['amount_cents']);
        $this->assertStringContainsString('checkout.stripe.com', $checkout['checkout_url']);
    }

    public function test_webhook_payment_succeeded_activates_slot_for_active_provider(): void
    {
        $metro = Metro::factory()->create();
        $category = ProviderCategory::factory()->create();
        $provider = Provider::factory()->create(['status' => 'active']);

        $slot = SponsoredSlot::create([
            'metro_id' => $metro->id,
            'category_id' => $category->id,
            'slot_number' => 1,
            'provider_id' => $provider->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'reserved',
        ]);

        $service = new StripeSlotBillingService();
        $result = $service->handleWebhook([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'metadata' => ['slot_id' => $slot->id],
                ],
            ],
        ]);

        $this->assertTrue($result);
        $this->assertEquals('active', $slot->fresh()->status);
    }

    public function test_webhook_payment_failed_cancels_slot(): void
    {
        $metro = Metro::factory()->create();
        $category = ProviderCategory::factory()->create();
        $provider = Provider::factory()->create(['status' => 'active']);

        $slot = SponsoredSlot::create([
            'metro_id' => $metro->id,
            'category_id' => $category->id,
            'slot_number' => 1,
            'provider_id' => $provider->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'reserved',
        ]);

        $service = new StripeSlotBillingService();
        $result = $service->handleWebhook([
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'metadata' => ['slot_id' => $slot->id],
                ],
            ],
        ]);

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $slot->fresh()->status);
    }
}
