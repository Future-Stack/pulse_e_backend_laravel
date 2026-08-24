<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CycleMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AvoidingPregnancyController extends Controller
{
    /**
     * Set Cycle Mode
     *
     * AI API:
     * POST /api/v1/cycle-engine/mode?user_id=2
     *
     * Body:
     * {
     *     "mode": "trying_to_conceive"
     * }
     */
    public function setMode(Request $request)
    {
        $request->validate([
            'mode' => [
                'required',
                'string',
                'in:cycle_awareness,trying_to_conceive,avoiding_pregnancy',
            ],
        ]);

        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userId = $user->id;

        $url = rtrim(config('services.ai.base_url'), '/')
            . '/api/v1/cycle-engine/mode';

        try {

            Log::info('Calling AI Cycle Mode API', [
                'url' => $url,
                'user_id' => $userId,
                'mode' => $request->mode,
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API Request
            |--------------------------------------------------------------------------
            |
            | Final URL:
            |
            | POST /api/v1/cycle-engine/mode?user_id=2
            |
            */

            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->withQueryParameters([
                    'user_id' => $userId,
                ])
                ->post($url, [
                    'mode' => $request->mode,
                ]);

            Log::info('AI Cycle Mode API Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API Error
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to set cycle mode.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $data = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Save Mode In Local Database
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use ($userId, $request) {

                CycleMode::updateOrCreate(
                    [
                        'user_id' => $userId,
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
                'message' => 'Cycle mode updated successfully.',
                'data' => $data,
            ]);

        } catch (\Throwable $e) {

            Log::error('Cycle Mode API Failed', [
                'user_id' => $userId,
                'mode' => $request->mode,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to set cycle mode.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Save Avoiding Pregnancy Consent
     *
     * AI API:
     * POST /api/v1/cycle-engine/avoiding-pregnancy/consent?user_id=2
     *
     * Body:
     * {
     *     "consented": true,
     *     "consent_version": "2026-07-cycle-engine-v1"
     * }
     */
    public function consent(Request $request)
    {
        $request->validate([
            'consented' => [
                'required',
                'boolean',
            ],
            'consent_version' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userId = $user->id;

        $url = rtrim(config('services.ai.base_url'), '/')
            . '/api/v1/cycle-engine/avoiding-pregnancy/consent';

        try {

            Log::info('Calling AI Consent API', [
                'url' => $url,
                'user_id' => $userId,
                'consented' => $request->boolean('consented'),
                'consent_version' => $request->consent_version,
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API Request
            |--------------------------------------------------------------------------
            |
            | Final URL:
            |
            | POST /api/v1/cycle-engine/avoiding-pregnancy/consent?user_id=2
            |
            */

            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->withQueryParameters([
                    'user_id' => $userId,
                ])
                ->post($url, [
                    'consented' => $request->boolean('consented'),
                    'consent_version' => $request->consent_version,
                ]);

            Log::info('AI Consent API Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API Error
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to save consent.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $data = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Save Consent In Local Database
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use ($userId, $data) {

                CycleMode::updateOrCreate(
                    [
                        'user_id' => $userId,
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
                'message' => 'Consent saved successfully.',
                'data' => $data,
            ]);

        } catch (\Throwable $e) {

            Log::error('Consent API Failed', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save consent.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Get Consent Status
     *
     * AI API:
     * GET /api/v1/cycle-engine/avoiding-pregnancy/consent-status?user_id=2
     */
    public function consentStatus(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userId = $user->id;

        $url = rtrim(config('services.ai.base_url'), '/')
            . '/api/v1/cycle-engine/avoiding-pregnancy/consent-status';

        try {

            Log::info('Calling AI Consent Status API', [
                'url' => $url,
                'user_id' => $userId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API Request
            |--------------------------------------------------------------------------
            |
            | Final URL:
            |
            | GET /api/v1/cycle-engine/avoiding-pregnancy/consent-status?user_id=2
            |
            */

            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->withQueryParameters([
                    'user_id' => $userId,
                ])
                ->get($url);

            Log::info('AI Consent Status API Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API Error
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch consent status.',
                    'error' => $response->json(),
                ], $response->status());
            }

            $data = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Save Consent Status In Local Database
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use ($userId, $data) {

                CycleMode::updateOrCreate(
                    [
                        'user_id' => $userId,
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
                'message' => 'Consent status fetched successfully.',
                'data' => $data,
            ]);

        } catch (\Throwable $e) {

            Log::error('Consent Status API Failed', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch consent status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

