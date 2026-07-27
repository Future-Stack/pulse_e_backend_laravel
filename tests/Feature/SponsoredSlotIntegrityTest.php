<?php

namespace Tests\Feature;

use App\Models\Metro;
use App\Models\Provider;
use App\Models\ProviderCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsoredSlotIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_active_provider_cannot_reserve_a_sponsored_slot(): void
    {
        $admin = User::factory()->create(['is_marketplace_admin' => true]);
        $metro = Metro::factory()->create();
        $category = ProviderCategory::factory()->create();
        $provider = Provider::factory()->create(['status' => 'candidate']); // not yet vetted/active

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            route('marketplace.admin.slots.reserve'),
            [
                'metro_id' => $metro->id,
                'category_id' => $category->id,
                'slot_number' => 1,
                'provider_id' => $provider->id,
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->addMonth()->toDateTimeString(),
            ]
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('sponsored_slots', 0);
    }

    public function test_the_same_slot_number_cannot_be_double_booked_for_overlapping_dates(): void
    {
        $admin = User::factory()->create(['is_marketplace_admin' => true]);
        $metro = Metro::factory()->create();
        $category = ProviderCategory::factory()->create();
        $providerA = Provider::factory()->create(['status' => 'active']);
        $providerB = Provider::factory()->create(['status' => 'active']);

        $this->actingAs($admin, 'sanctum')->postJson(route('marketplace.admin.slots.reserve'), [
            'metro_id' => $metro->id,
            'category_id' => $category->id,
            'slot_number' => 1,
            'provider_id' => $providerA->id,
            'starts_at' => now()->toDateTimeString(),
            'ends_at' => now()->addMonths(3)->toDateTimeString(),
        ])->assertStatus(201);

        $response = $this->actingAs($admin, 'sanctum')->postJson(route('marketplace.admin.slots.reserve'), [
            'metro_id' => $metro->id,
            'category_id' => $category->id,
            'slot_number' => 1, // same slot, overlapping window
            'provider_id' => $providerB->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonths(4)->toDateTimeString(),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sponsored_slots', 1);
    }

    public function test_non_admin_authenticated_user_is_forbidden_from_admin_routes(): void
    {
        $regularUser = User::factory()->create(['is_marketplace_admin' => false]);

        $response = $this->actingAs($regularUser, 'sanctum')
            ->getJson(route('marketplace.admin.providers.index'));

        $response->assertStatus(403);
    }
}
