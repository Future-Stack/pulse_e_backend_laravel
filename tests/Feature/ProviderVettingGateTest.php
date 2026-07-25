<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderVettingGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_medical_tier_provider_is_not_fully_vetted_without_license_and_leie_checks(): void
    {
        $provider = Provider::factory()->create(['status' => 'candidate']);
        $category = ProviderCategory::factory()->create(['vetting_tier' => 'medical']);
        $provider->categories()->attach($category->id, ['source' => 'manual']);

        $this->assertFalse($provider->fresh()->isFullyVetted());
    }

    public function test_medical_tier_provider_is_fully_vetted_once_license_and_leie_pass(): void
    {
        $provider = Provider::factory()->create(['status' => 'candidate']);
        $category = ProviderCategory::factory()->create(['vetting_tier' => 'medical']);
        $provider->categories()->attach($category->id, ['source' => 'manual']);

        $provider->vettingRecords()->create(['check_type' => 'license', 'status' => 'pass', 'checked_at' => now()]);
        $provider->vettingRecords()->create(['check_type' => 'leie', 'status' => 'pass', 'checked_at' => now()]);

        $this->assertTrue($provider->fresh()->isFullyVetted());
    }

    public function test_expired_check_no_longer_counts_as_passing(): void
    {
        $provider = Provider::factory()->create(['status' => 'candidate']);
        $category = ProviderCategory::factory()->create(['vetting_tier' => 'licensed_nonmedical']);
        $provider->categories()->attach($category->id, ['source' => 'manual']);

        $provider->vettingRecords()->create([
            'check_type' => 'license',
            'status' => 'pass',
            'checked_at' => now()->subYear(),
            'next_due_at' => now()->subDay(), // already past due
        ]);

        $this->assertFalse($provider->fresh()->isFullyVetted());
    }

    public function test_admin_cannot_activate_a_provider_that_is_not_fully_vetted(): void
    {
        $admin = \App\Models\User::factory()->create(['is_marketplace_admin' => true]);
        $provider = Provider::factory()->create(['status' => 'candidate']);
        $category = ProviderCategory::factory()->create(['vetting_tier' => 'medical']);
        $provider->categories()->attach($category->id, ['source' => 'manual']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson(route('marketplace.admin.providers.status', $provider), ['status' => 'active']);

        $response->assertStatus(422);
        $this->assertSame('candidate', $provider->fresh()->status);
    }
}
