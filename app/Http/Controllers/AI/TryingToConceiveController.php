<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenstrualCycle;
use App\Models\TtcPrediction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TryingToConceiveController extends Controller
{
    /**
     * LH Surge Banner
     */
    public function surgeBanner(Request $request)
    {
        $user = auth()->user();

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/ttc/surge-banner';

        $response = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->get($url);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch surge banner.',
                'error' => $response->body(),
            ], 500);
        }

        $data = $response->json();

        DB::transaction(function () use ($user, $data) {

            $cycle = MenstrualCycle::where('user_id', $user->id)
                ->where('is_completed', false)
                ->latest()
                ->first();

            if ($cycle) {
                TtcPrediction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'cycle_day' => $data['cycle_day'] ?? null,
                        'surge_active' => $data['active'] ?? false,
                        'surge_message' => $data['message'] ?? null,
                        'hours_remaining_estimate' => $data['hours_remaining_estimate'] ?? null,
                        'lh_surge_day' => $data['lh_surge_day'] ?? null,
                        'ai_generated' => $data['ai_generated'] ?? false,
                        'ai_fallback' => $data['ai_fallback'] ?? false,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * TTC Priority Map
     */
    public function priorityMap(Request $request)
    {
        $user = auth()->user();

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/ttc/priority-map';

        $response = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->get($url);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch priority map.',
                'error' => $response->body(),
            ], 500);
        }

        $data = $response->json();

        DB::transaction(function () use ($user, $data) {

            $cycle = MenstrualCycle::where('user_id', $user->id)
                ->where('is_completed', false)
                ->latest()
                ->first();

            if ($cycle) {
                TtcPrediction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'cycle_day' => $data['cycle_day'] ?? null,
                        'priority_ranges' => $data['ranges'] ?? [],
                        'ai_generated' => $data['ai_generated'] ?? false,
                        'ai_fallback' => $data['ai_fallback'] ?? false,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * TTC Priority Banner
     */
    public function priorityBanner(Request $request)
    {
        $user = auth()->user();

        $url = config('services.ai.base_url')
            . '/api/v1/cycle-engine/ttc/priority-banner';

        $response = Http::timeout(120)
            ->acceptJson()
            ->withToken($request->bearerToken())
            ->get($url);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch priority banner.',
                'error' => $response->body(),
            ], 500);
        }

        $data = $response->json();

        DB::transaction(function () use ($user, $data) {

            $cycle = MenstrualCycle::where('user_id', $user->id)
                ->where('is_completed', false)
                ->latest()
                ->first();

            if ($cycle) {
                TtcPrediction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'cycle_day' => $data['cycle_day'] ?? null,
                        'priority' => $data['priority'] ?? null,
                        'label' => $data['label'] ?? null,
                        'priority_message' => $data['message'] ?? null,
                        'ai_generated' => $data['ai_generated'] ?? false,
                        'ai_fallback' => $data['ai_fallback'] ?? false,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}