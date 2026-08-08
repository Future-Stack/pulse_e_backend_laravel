<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenstrualCycle;
use App\Models\TtcPrediction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TryingToConceiveController extends Controller
{
    /**
     * Sync all TTC data
     *
     * This endpoint calls:
     *
     * 1. /ttc/surge-banner
     * 2. /ttc/priority-map
     * 3. /ttc/priority-banner
     *
     * and stores the combined result in ttc_predictions.
     *
     * Backend:
     * GET /api/v1/cycle-engine/ttc/sync
     */
    public function sync()
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Get Active Cycle
        |--------------------------------------------------------------------------
        */

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->where('is_completed', false)
            ->latest('id')
            ->first();

        if (! $cycle) {
            return response()->json([
                'success' => false,
                'message' => 'No active menstrual cycle found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. AI Engine Base URL
        |--------------------------------------------------------------------------
        */

        $baseUrl = rtrim(
            config(
                'services.ai.base_url',
                'https://ai.fightthenumber.com'
            ),
            '/'
        );

        try {

            Log::info('Starting TTC Sync', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'base_url' => $baseUrl,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 3. Call All TTC AI Endpoints
            |--------------------------------------------------------------------------
            |
            | All three requests are sent in parallel.
            |
            */

            $responses = Http::pool(function ($pool) use (
                $baseUrl,
                $user
            ) {
                return [

                    // Surge Banner
                    $pool->timeout(120)
                        ->acceptJson()
                        ->get(
                            $baseUrl . '/api/v1/cycle-engine/ttc/surge-banner',
                            [
                                'user_id' => $user->id,
                            ]
                        ),

                    // Priority Map
                    $pool->timeout(120)
                        ->acceptJson()
                        ->get(
                            $baseUrl . '/api/v1/cycle-engine/ttc/priority-map',
                            [
                                'user_id' => $user->id,
                            ]
                        ),

                    // Priority Banner
                    $pool->timeout(120)
                        ->acceptJson()
                        ->get(
                            $baseUrl . '/api/v1/cycle-engine/ttc/priority-banner',
                            [
                                'user_id' => $user->id,
                            ]
                        ),
                ];
            });

            $surgeResponse = $responses[0] ?? null;
            $priorityMapResponse = $responses[1] ?? null;
            $priorityBannerResponse = $responses[2] ?? null;

            /*
            |--------------------------------------------------------------------------
            | 4. Log AI Responses
            |--------------------------------------------------------------------------
            */

            Log::info('TTC AI Responses', [
                'user_id' => $user->id,

                'surge_banner' => [
                    'status' => $surgeResponse?->status(),
                    'response' => $surgeResponse?->json(),
                ],

                'priority_map' => [
                    'status' => $priorityMapResponse?->status(),
                    'response' => $priorityMapResponse?->json(),
                ],

                'priority_banner' => [
                    'status' => $priorityBannerResponse?->status(),
                    'response' => $priorityBannerResponse?->json(),
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 5. Check AI Engine Responses
            |--------------------------------------------------------------------------
            */

            $failedEndpoints = [];

            if (! $surgeResponse || ! $surgeResponse->successful()) {
                $failedEndpoints['surge_banner'] = [
                    'status' => $surgeResponse?->status(),
                    'error' => $surgeResponse?->json()
                        ?? $surgeResponse?->body(),
                ];
            }

            if (! $priorityMapResponse || ! $priorityMapResponse->successful()) {
                $failedEndpoints['priority_map'] = [
                    'status' => $priorityMapResponse?->status(),
                    'error' => $priorityMapResponse?->json()
                        ?? $priorityMapResponse?->body(),
                ];
            }

            if (! $priorityBannerResponse || ! $priorityBannerResponse->successful()) {
                $failedEndpoints['priority_banner'] = [
                    'status' => $priorityBannerResponse?->status(),
                    'error' => $priorityBannerResponse?->json()
                        ?? $priorityBannerResponse?->body(),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Return AI Error Clearly
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | {
            |   "success": false,
            |   "message": "Unable to sync TTC data from AI Engine.",
            |   "status": 502,
            |   "error": {
            |       "surge_banner": {
            |           "status": 502,
            |           "error": {
            |               "detail": "Unable to load cycle calendar inputs for user 2"
            |           }
            |       }
            |   }
            | }
            |
            */

            if (! empty($failedEndpoints)) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to sync TTC data from AI Engine.',
                    'status' => 502,
                    'error' => $failedEndpoints,
                ], 502);
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Get AI Data
            |--------------------------------------------------------------------------
            */

            $surgeData = $surgeResponse->json();
            $priorityMapData = $priorityMapResponse->json();
            $priorityBannerData = $priorityBannerResponse->json();

            /*
            |--------------------------------------------------------------------------
            | 8. Save TTC Prediction
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use (
                $user,
                $cycle,
                $surgeData,
                $priorityMapData,
                $priorityBannerData
            ) {

                TtcPrediction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [

                        /*
                        |--------------------------------------------------------------------------
                        | Cycle Day
                        |--------------------------------------------------------------------------
                        */

                        'cycle_day' =>
                            $surgeData['cycle_day']
                            ?? $priorityMapData['cycle_day']
                            ?? $priorityBannerData['cycle_day']
                            ?? $cycle->current_cycle_day
                            ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | Surge Banner
                        |--------------------------------------------------------------------------
                        */

                        'surge_active' =>
                            $surgeData['active'] ?? false,

                        'surge_message' =>
                            $surgeData['message'] ?? null,

                        'hours_remaining_estimate' =>
                            $surgeData['hours_remaining_estimate'] ?? null,

                        'lh_surge_day' =>
                            $surgeData['lh_surge_day'] ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | Priority Map
                        |--------------------------------------------------------------------------
                        */

                        'priority_ranges' =>
                            $priorityMapData['ranges'] ?? [],

                        /*
                        |--------------------------------------------------------------------------
                        | Priority Banner
                        |--------------------------------------------------------------------------
                        */

                        'priority' =>
                            $priorityBannerData['priority'] ?? null,

                        'label' =>
                            $priorityBannerData['label'] ?? null,

                        'priority_message' =>
                            $priorityBannerData['message'] ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | AI Metadata
                        |--------------------------------------------------------------------------
                        */

                        'ai_generated' =>
                            $surgeData['ai_generated']
                            ?? $priorityMapData['ai_generated']
                            ?? $priorityBannerData['ai_generated']
                            ?? false,

                        'ai_fallback' =>
                            $surgeData['ai_cached']
                            ?? $priorityMapData['ai_cached']
                            ?? $priorityBannerData['ai_cached']
                            ?? false,
                    ]
                );
            });

            /*
            |--------------------------------------------------------------------------
            | 9. Get Saved Prediction
            |--------------------------------------------------------------------------
            */

            $prediction = TtcPrediction::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first();

            /*
            |--------------------------------------------------------------------------
            | 10. Final Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'TTC data synced successfully.',
                'data' => [
                    'surge_banner' => $surgeData,
                    'priority_map' => $priorityMapData,
                    'priority_banner' => $priorityBannerData,
                    'prediction' => $prediction,
                ],
            ]);

        } catch (\Throwable $e) {

            Log::error('TTC Sync Failed', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC data.',
                'status' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * LH Surge Banner
     *
     * GET /api/v1/cycle-engine/ttc/surge-banner
     */
    public function surgeBanner(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->where('is_completed', false)
            ->latest('id')
            ->first();

        if (! $cycle) {
            return response()->json([
                'success' => false,
                'message' => 'No active menstrual cycle found.',
            ], 404);
        }

        try {

            $url = rtrim(
                config(
                    'services.ai.base_url',
                    'https://ai.fightthenumber.com'
                ),
                '/'
            ) . '/api/v1/cycle-engine/ttc/surge-banner';

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $user->id,
                ]);

            if (! $response->successful()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch surge banner from AI Engine.',
                    'status' => $response->status(),
                    'error' => $response->json() ?? $response->body(),
                ], $response->status());
            }

            $data = $response->json();

            DB::transaction(function () use (
                $user,
                $cycle,
                $data
            ) {

                TtcPrediction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'cycle_day' =>
                            $data['cycle_day'] ?? null,

                        'surge_active' =>
                            $data['active'] ?? false,

                        'surge_message' =>
                            $data['message'] ?? null,

                        'hours_remaining_estimate' =>
                            $data['hours_remaining_estimate'] ?? null,

                        'lh_surge_day' =>
                            $data['lh_surge_day'] ?? null,

                        'ai_generated' =>
                            $data['ai_generated'] ?? false,

                        'ai_fallback' =>
                            $data['ai_cached'] ?? false,
                    ]
                );
            });

            $prediction = TtcPrediction::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'TTC surge banner synced successfully.',
                'data' => $prediction,
            ]);

        } catch (\Throwable $e) {

            Log::error('TTC Surge Banner Sync Failed', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC surge banner.',
                'status' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * TTC Priority Map
     *
     * GET /api/v1/cycle-engine/ttc/priority-map
     */
    public function priorityMap(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->where('is_completed', false)
            ->latest('id')
            ->first();

        if (! $cycle) {
            return response()->json([
                'success' => false,
                'message' => 'No active menstrual cycle found.',
            ], 404);
        }

        try {

            $url = rtrim(
                config(
                    'services.ai.base_url',
                    'https://ai.fightthenumber.com'
                ),
                '/'
            ) . '/api/v1/cycle-engine/ttc/priority-map';

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $user->id,
                ]);

            if (! $response->successful()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch TTC priority map from AI Engine.',
                    'status' => $response->status(),
                    'error' => $response->json() ?? $response->body(),
                ], $response->status());
            }

            $data = $response->json();

            DB::transaction(function () use (
                $user,
                $cycle,
                $data
            ) {

                TtcPrediction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'cycle_day' =>
                            $data['cycle_day'] ?? null,

                        'priority_ranges' =>
                            $data['ranges'] ?? [],

                        'ai_generated' =>
                            $data['ai_generated'] ?? false,

                        'ai_fallback' =>
                            $data['ai_cached'] ?? false,
                    ]
                );
            });

            $prediction = TtcPrediction::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'TTC priority map synced successfully.',
                'data' => $prediction,
            ]);

        } catch (\Throwable $e) {

            Log::error('TTC Priority Map Sync Failed', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC priority map.',
                'status' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * TTC Priority Banner
     *
     * GET /api/v1/cycle-engine/ttc/priority-banner
     */
    public function priorityBanner(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->where('is_completed', false)
            ->latest('id')
            ->first();

        if (! $cycle) {
            return response()->json([
                'success' => false,
                'message' => 'No active menstrual cycle found.',
            ], 404);
        }

        try {

            $url = rtrim(
                config(
                    'services.ai.base_url',
                    'https://ai.fightthenumber.com'
                ),
                '/'
            ) . '/api/v1/cycle-engine/ttc/priority-banner';

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $user->id,
                ]);

            if (! $response->successful()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch TTC priority banner from AI Engine.',
                    'status' => $response->status(),
                    'error' => $response->json() ?? $response->body(),
                ], $response->status());
            }

            $data = $response->json();

            DB::transaction(function () use (
                $user,
                $cycle,
                $data
            ) {

                TtcPrediction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'cycle_day' =>
                            $data['cycle_day'] ?? null,

                        'priority' =>
                            $data['priority'] ?? null,

                        'label' =>
                            $data['label'] ?? null,

                        'priority_message' =>
                            $data['message'] ?? null,

                        'ai_generated' =>
                            $data['ai_generated'] ?? false,

                        'ai_fallback' =>
                            $data['ai_cached'] ?? false,
                    ]
                );
            });

            $prediction = TtcPrediction::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'TTC priority banner synced successfully.',
                'data' => $prediction,
            ]);

        } catch (\Throwable $e) {

            Log::error('TTC Priority Banner Sync Failed', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC priority banner.',
                'status' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
