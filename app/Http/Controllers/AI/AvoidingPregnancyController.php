<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CycleMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AvoidingPregnancyController extends Controller
{
    /**
     * Set Cycle Mode
     */
    public function setMode(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:cycle_awareness,trying_to_conceive,avoiding_pregnancy',
        ]);

        $user = auth()->user();

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/mode';

        $response = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->post($url, [
                'mode' => $request->mode,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to set cycle mode.',
                'error' => $response->body(),
            ], 500);
        }

        DB::transaction(function () use ($user, $request) {

            CycleMode::updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'mode' => $request->mode,
                    'is_active' => true,
                    'activated_at' => now(),
                ]
            );

        });

        return response()->json([
            'success' => true,
            'data' => $response->json(),
        ]);
    }

    /**
     * Save Consent
     */
    public function consent(Request $request)
    {
        $request->validate([
            'consented' => 'required|boolean',
            'consent_version' => 'required|string',
        ]);

        $user = auth()->user();

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/avoiding-pregnancy/consent';

        $response = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->post($url, [
                'consented' => $request->boolean('consented'),
                'consent_version' => $request->consent_version,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to save consent.',
                'error' => $response->body(),
            ], 500);
        }

        $data = $response->json();

        DB::transaction(function () use ($user, $data) {

            CycleMode::updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'has_consented' => $data['has_consented'] ?? false,
                    'consent_version' => $data['consent_version'] ?? null,
                    'consented_at' => $data['consented_at'] ?? null,
                ]
            );

        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Consent Status
     */
    public function consentStatus(Request $request)
    {
        $user = auth()->user();

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/avoiding-pregnancy/consent-status';

        $response = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->get($url);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch consent status.',
                'error' => $response->body(),
            ], 500);
        }

        $data = $response->json();

        DB::transaction(function () use ($user, $data) {

            CycleMode::updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'has_consented' => $data['has_consented'] ?? false,
                    'consent_version' => $data['consent_version'] ?? null,
                    'consented_at' => $data['consented_at'] ?? null,
                ]
            );

        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}