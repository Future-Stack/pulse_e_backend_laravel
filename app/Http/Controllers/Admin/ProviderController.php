<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD: /admin/providers — review queue and status transitions.
 * Payment/monetization never bypasses vetting: only isFullyVetted() providers
 * may transition into 'active' (enforced in transitionStatus()).
 */
class ProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $providers = Provider::query()
            ->with('categories', 'placeDetailsCache')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('metro_id'), fn ($q) => $q->where('metro_id', $request->integer('metro_id')))
            ->when($request->filled('category'), function ($q) use ($request) {
                $q->whereHas('categories', fn ($c) => $c->where('slug', $request->string('category')));
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($providers);
    }

    public function show(Provider $provider): JsonResponse
    {
        $provider->load('categories', 'placeDetailsCache', 'vettingRecords', 'metro');

        return response()->json(['data' => $provider]);
    }

    public function update(Request $request, Provider $provider): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:160'],
            'org_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'google_place_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'npi' => ['sometimes', 'nullable', 'string', 'size:10'],
            'phone_e164' => ['sometimes', 'nullable', 'string', 'max:20'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'addr_line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'addr_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:50'],
            'zip' => ['sometimes', 'nullable', 'string', 'max:20'],
            'metro_id' => ['sometimes', 'nullable', 'integer', 'exists:metros,id'],
        ]);

        $provider->update($data);

        return response()->json(['data' => $provider]);
    }

    public function transitionStatus(Request $request, Provider $provider): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:candidate,vetted,active,suspended,excluded'],
        ]);

        if ($data['status'] === 'active' && ! $provider->isFullyVetted()) {
            return response()->json([
                'message' => 'Provider cannot become active until all mandatory vetting checks pass.',
            ], 422);
        }

        // 'excluded' is terminal without manual override (spec 2.3, leie:screen job).
        if ($provider->status === 'excluded' && $data['status'] !== 'excluded') {
            return response()->json([
                'message' => 'Excluded providers require a manual override to be reinstated.',
            ], 422);
        }

        $provider->update(['status' => $data['status']]);

        return response()->json(['data' => $provider]);
    }
}
