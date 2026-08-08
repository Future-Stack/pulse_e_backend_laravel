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
     * Sync all TTC data (Surge Banner, Priority Map, Priority Banner) from AI engine.
     *
     * GET /api/v1/ttc/sync
     */
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

            Log::info('Calling TTC AI APIs (Surge Banner, Priority Map, Priority Banner)', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
            ]);

            $responses = Http::pool(fn ($pool) => [
                $pool->timeout(120)->acceptJson()->get("{$baseUrl}/api/v1/cycle-engine/ttc/surge-banner", ['user_id' => $user->id]),
                $pool->timeout(120)->acceptJson()->get("{$baseUrl}/api/v1/cycle-engine/ttc/priority-map", ['user_id' => $user->id]),
                $pool->timeout(120)->acceptJson()->get("{$baseUrl}/api/v1/cycle-engine/ttc/priority-banner", ['user_id' => $user->id]),
            ]);

            $surgeRes = $responses[0] ?? null;
            $mapRes = $responses[1] ?? null;
            $bannerRes = $responses[2] ?? null;

            Log::info('TTC AI Responses Received', [
                'user_id' => $user->id,
                'surge_status' => $surgeRes ? $surgeRes->status() : null,
                'priorityMap_status' => $mapRes ? $mapRes->status() : null,
                'priorityBanner_status' => $bannerRes ? $bannerRes->status() : null,
            ]);

            $updateData = [];
            $hasSuccess = false;

            if ($surgeRes && $surgeRes->successful()) {
                $hasSuccess = true;
                $surgeData = $surgeRes->json();
                if (isset($surgeData['cycle_day'])) {
                    $updateData['cycle_day'] = $surgeData['cycle_day'];
                }
                $updateData['surge_active'] = $surgeData['active'] ?? false;
                $updateData['surge_message'] = $surgeData['message'] ?? null;
                $updateData['hours_remaining_estimate'] = $surgeData['hours_remaining_estimate'] ?? null;
                $updateData['lh_surge_day'] = $surgeData['lh_surge_day'] ?? null;
                if (isset($surgeData['ai_generated'])) {
                    $updateData['ai_generated'] = $surgeData['ai_generated'];
                }
                if (isset($surgeData['ai_cached'])) {
                    $updateData['ai_fallback'] = $surgeData['ai_cached'];
                }
            }

            if ($mapRes && $mapRes->successful()) {
                $hasSuccess = true;
                $mapData = $mapRes->json();
                if (isset($mapData['cycle_day'])) {
                    $updateData['cycle_day'] = $mapData['cycle_day'];
                }
                $updateData['priority_ranges'] = $mapData['ranges'] ?? [];
                if (isset($mapData['ai_generated'])) {
                    $updateData['ai_generated'] = $mapData['ai_generated'];
                }
                if (isset($mapData['ai_cached'])) {
                    $updateData['ai_fallback'] = $mapData['ai_cached'];
                }
            }

            if ($bannerRes && $bannerRes->successful()) {
                $hasSuccess = true;
                $bannerData = $bannerRes->json();
                if (isset($bannerData['cycle_day'])) {
                    $updateData['cycle_day'] = $bannerData['cycle_day'];
                }
                $updateData['priority'] = $bannerData['priority'] ?? null;
                $updateData['label'] = $bannerData['label'] ?? null;
                $updateData['priority_message'] = $bannerData['message'] ?? null;
                if (isset($bannerData['ai_generated'])) {
                    $updateData['ai_generated'] = $bannerData['ai_generated'];
                }
                if (isset($bannerData['ai_cached'])) {
                    $updateData['ai_fallback'] = $bannerData['ai_cached'];
                }
            }

            if (! $hasSuccess) {
                Log::warning('AI Engine TTC calls failed or returned non-200 responses, applying local fallback', [
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                    'surge_error' => $surgeRes ? $surgeRes->json() : null,
                    'map_error' => $mapRes ? $mapRes->json() : null,
                    'banner_error' => $bannerRes ? $bannerRes->json() : null,
                ]);

                $prediction = TtcPrediction::where('user_id', $user->id)
                    ->where('cycle_id', $cycle->id)
                    ->first();

                if (! $prediction) {
                    $startDate = $cycle->period_start_date ? \Carbon\Carbon::parse($cycle->period_start_date) : today();
                    $currentCycleDay = max(1, (int) $startDate->diffInDays(today()) + 1);

                    $phase = match (true) {
                        $currentCycleDay <= 5 => 'menstrual',
                        $currentCycleDay <= 13 => 'follicular',
                        $currentCycleDay <= 16 => 'ovulatory',
                        default => 'luteal',
                    };

                    $prediction = TtcPrediction::create([
                        'user_id' => $user->id,
                        'cycle_id' => $cycle->id,
                        'cycle_day' => $currentCycleDay,
                        'surge_active' => false,
                        'surge_message' => 'No active LH surge detected yet. Continue logging your OPK and BBT data.',
                        'hours_remaining_estimate' => null,
                        'lh_surge_day' => null,
                        'priority' => ucfirst($phase),
                        'label' => ucfirst($phase) . ' phase',
                        'priority_message' => "You're on day {$currentCycleDay} of your cycle ({$phase} phase). Log data to track fertility signals.",
                        'priority_ranges' => [],
                        'ai_generated' => false,
                        'ai_fallback' => true,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'TTC predictions synced with local cycle fallback.',
                    'data' => $prediction,
                ]);
            }

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
                'message' => 'TTC data synced successfully.',
                'data' => $prediction,
            ]);

        } catch (\Throwable $e) {
            Log::error('TTC Sync Failed Exception', [
                'user_id' => $user->id,
                'cycle_id' => $cycle->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync TTC data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * LH Surge Banner
     */
    public function surgeBanner(Request $request)
    {
        return $this->syncTtcData($request);
    }

    /**
     * TTC Priority Map
     */
    public function priorityMap(Request $request)
    {
        return $this->syncTtcData($request);
    }

    /**
     * TTC Priority Banner
     */
    public function priorityBanner(Request $request)
    {
        return $this->syncTtcData($request);
    }
}
