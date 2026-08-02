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
     * LH Surge Banner
     *
     * AI:
     * GET /api/v1/cycle-engine/ttc/surge-banner?user_id={user_id}
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
                config('services.ai.base_url'),
                '/'
            ) . '/api/v1/cycle-engine/ttc/surge-banner';

            Log::info('Calling TTC Surge Banner AI API', [
                'url' => $url,
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            | AI API requires user_id as QUERY PARAMETER.
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $user->id,
                ]);

            Log::info('TTC Surge Banner AI Response', [
                'status' => $response->status(),
                'user_id' => $user->id,
                'response' => $response->json(),
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch surge banner.',
                    'error' => $response->json(),
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

                        /*
                        | AI response has ai_cached,
                        | not ai_fallback.
                        */
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
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC surge banner.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * TTC Priority Map
     *
     * AI:
     * GET /api/v1/cycle-engine/ttc/priority-map?user_id={user_id}
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
                config('services.ai.base_url'),
                '/'
            ) . '/api/v1/cycle-engine/ttc/priority-map';

            Log::info('Calling TTC Priority Map AI API', [
                'url' => $url,
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            | Send user_id as QUERY PARAMETER.
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $user->id,
                ]);

            Log::info('TTC Priority Map AI Response', [
                'status' => $response->status(),
                'user_id' => $user->id,
                'response' => $response->json(),
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch priority map.',
                    'error' => $response->json(),
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

                        /*
                        | AI response:
                        | "ranges": [...]
                        */
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
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC priority map.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * TTC Priority Banner
     *
     * AI:
     * GET /api/v1/cycle-engine/ttc/priority-banner?user_id={user_id}
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
                config('services.ai.base_url'),
                '/'
            ) . '/api/v1/cycle-engine/ttc/priority-banner';

            Log::info('Calling TTC Priority Banner AI API', [
                'url' => $url,
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            | Send user_id as QUERY PARAMETER.
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $user->id,
                ]);

            Log::info('TTC Priority Banner AI Response', [
                'status' => $response->status(),
                'user_id' => $user->id,
                'response' => $response->json(),
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch priority banner.',
                    'error' => $response->json(),
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
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC priority banner.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}