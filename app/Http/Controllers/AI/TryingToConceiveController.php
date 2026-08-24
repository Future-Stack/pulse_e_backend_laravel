<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenstrualCycle;
use App\Models\TtcPrediction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TryingToConceiveController extends Controller
{
    /**
     * Sync all TTC data using the single combined AI "overview" endpoint.
     * Replaces the need for 3 separate calls (surge-banner, priority-map, priority-banner).
     *
     * GET /api/v1/ttc/sync-overview
     */
    public function syncTtcOverview(Request $request)
    {
        return $this->syncTtcData($request);
    }

    public function syncTtcData(Request $request)
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
            $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

            Log::info('Calling TTC AI Overview API', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
            ]);

            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->get("{$baseUrl}/api/v1/cycle-engine/ttc/overview", [
                    'user_id' => $user->id,
                ]);

            Log::info('TTC AI Overview Response Received', [
                'user_id' => $user->id,
                'status' => $response->status(),
            ]);

            if (! $response->successful()) {
                Log::warning('AI Engine TTC overview call failed, applying local fallback', [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                    'error' => $response->json(),
                ]);

                return $this->applyLocalFallback($user, $cycle);
            }

            $data = $response->json();

            $surge = $data['surge_banner'] ?? [];
            $map = $data['priority_map'] ?? [];
            $banner = $data['priority_banner'] ?? [];

            if (empty($surge) && empty($map) && empty($banner)) {
                Log::warning('AI Engine TTC overview returned empty payload, applying local fallback', [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                ]);

                return $this->applyLocalFallback($user, $cycle);
            }

            // Extract cycle day safely across blocks
            $cycleDay = $surge['cycle_day'] ?? $map['cycle_day'] ?? $banner['cycle_day'] ?? 1;

            $updateData = [
                'cycle_day' => $cycleDay,
                
                // Surge banner mapping
                'surge_active' => $surge['active'] ?? false,
                'surge_message' => $surge['message'] ?? null,
                'hours_remaining_estimate' => $surge['hours_remaining_estimate'] ?? null,
                'lh_surge_day' => $surge['lh_surge_day'] ?? null,

                // Priority map mapping
                'priority_ranges' => $map['ranges'] ?? [],

                // Priority banner mapping
                'priority' => $banner['priority'] ?? null,
                'label' => $banner['label'] ?? null,
                'priority_message' => $banner['message'] ?? null,

                // Flags
                'ai_generated' => $surge['ai_generated'] ?? $map['ai_generated'] ?? $banner['ai_generated'] ?? true,
                'ai_fallback' => false,
            ];

            DB::transaction(function () use ($user, $cycle, $updateData) {
                TtcPrediction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                    ],
                    $updateData
                );
            });

            $prediction = TtcPrediction::where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'TTC overview data synced successfully.',
                'data' => $prediction,
            ]);

        } catch (\Throwable $e) {
            Log::error('TTC Overview Sync Failed Exception', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC overview data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function surgeBanner(Request $request)
    {
        return $this->syncTtcData($request);
    }

    public function priorityMap(Request $request)
    {
        return $this->syncTtcData($request);
    }

    public function priorityBanner(Request $request)
    {
        return $this->syncTtcData($request);
    }

    /**
     * Shared local fallback logic when AI engine is unreachable or returns empty data.
     */
    private function applyLocalFallback($user, $cycle)
    {
        $startDate = $cycle->period_start_date ? Carbon::parse($cycle->period_start_date) : today();
        $currentCycleDay = max(1, (int) $startDate->diffInDays(today()) + 1);

        $phase = match (true) {
            $currentCycleDay <= 5 => 'menstrual',
            $currentCycleDay <= 13 => 'follicular',
            $currentCycleDay <= 16 => 'ovulatory',
            default => 'luteal',
        };

        $fallbackRanges = [
            [
                'start_day' => 10,
                'end_day' => 16,
                'label' => 'Estimated Fertile Window',
                'priority' => 'high',
            ],
        ];

        $prediction = TtcPrediction::updateOrCreate(
            [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
            ],
            [
                'cycle_day' => $currentCycleDay,
                'surge_active' => false,
                'surge_message' => 'No active LH surge detected yet. Continue logging your OPK and BBT data.',
                'hours_remaining_estimate' => null,
                'lh_surge_day' => null,
                'priority' => ucfirst($phase),
                'label' => ucfirst($phase) . ' phase',
                'priority_message' => "You're on day {$currentCycleDay} of your cycle ({$phase} phase). Log data to track fertility signals.",
                'priority_ranges' => $fallbackRanges,
                'ai_generated' => false,
                'ai_fallback' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'TTC predictions synced with local cycle fallback.',
            'data' => $prediction,
        ]);
    }
}