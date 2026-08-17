<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\SponsoredSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin CRUD: /admin/slots — B2B phase. Enforces the "3-per-metro+category" rule
 * and the "sponsored providers must be fully vetted / active" rule from spec 2.2/2.6:
 * a slot can only ever reference an active (fully vetted) provider.
 */
class SponsoredSlotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $slots = SponsoredSlot::query()
            ->with('metro', 'category', 'provider')
            ->when($request->filled('metro_id'), fn ($q) => $q->where('metro_id', $request->integer('metro_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('metro_id')->orderBy('category_id')->orderBy('slot_number')
            ->paginate($request->integer('per_page', 25));

        return response()->json($slots);
    }

    public function reserve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metro_id' => ['required', 'integer', 'exists:metros,id'],
            'category_id' => ['required', 'integer', 'exists:provider_categories,id'],
            'slot_number' => ['required', 'integer', 'min:1', 'max:3'],
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'monthly_rate_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $provider = Provider::findOrFail($data['provider_id']);

        if ($provider->status !== 'active') {
            throw ValidationException::withMessages([
                'provider_id' => 'Only providers with status = active can hold a sponsored slot. Payment never bypasses vetting.',
            ]);
        }

        $conflict = SponsoredSlot::query()
            ->where('metro_id', $data['metro_id'])
            ->where('category_id', $data['category_id'])
            ->where('slot_number', $data['slot_number'])
            ->whereIn('status', ['reserved', 'active'])
            ->where('ends_at', '>', $data['starts_at'])
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'slot_number' => 'This slot is already reserved or active for the given date range.',
            ]);
        }

        $data['status'] = 'reserved';
        $slot = SponsoredSlot::create($data);

        $billingService = app(\App\Services\StripeSlotBillingService::class);
        $checkout = $billingService->createCheckoutSession(
            $slot,
            url("/admin/slots/{$slot->id}/success"),
            url("/admin/slots/{$slot->id}/cancel")
        );

        return response()->json([
            'data' => $slot,
            'checkout' => $checkout,
        ], 201);
    }

    public function activate(SponsoredSlot $slot): JsonResponse
    {
        if ($slot->provider->status !== 'active') {
            throw ValidationException::withMessages([
                'provider_id' => 'Provider must be fully vetted and active before its slot can activate.',
            ]);
        }

        $slot->update(['status' => 'active']);

        return response()->json(['data' => $slot]);
    }

    public function cancel(SponsoredSlot $slot): JsonResponse
    {
        $slot->update(['status' => 'cancelled']);

        return response()->json(['data' => $slot]);
    }
}
