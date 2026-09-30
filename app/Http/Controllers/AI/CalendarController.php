<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CycleCalendarInput;
use App\Models\MenstrualCycle;
use App\Models\CycleStatistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    /**
     * Get calendar month from AI Engine.
     *
     * AI endpoint:
     * GET /api/v1/cycle-engine/calendar/month?user_id={user_id}
     */
    public function syncMonth(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $url = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/')
            . '/api/v1/cycle-engine/calendar/month';

        $userId = $request->query('user_id', $user->id);
        $userSettings = \App\Services\CycleCalculatorService::getUserCycleSettings($userId);
        $cycleLength = $userSettings['cycle_length'];
        $lutealLength = $userSettings['luteal_phase_length'];

        $queryParams = array_merge([
            'cycle_length' => $cycleLength,
            'average_cycle_length' => $cycleLength,
            'luteal_phase_length' => $lutealLength,
        ], $request->query(), ['user_id' => $userId]);

        try {
            Log::info('Calling AI Calendar Month API', [
                'url' => $url,
                'user_id' => $userId,
                'query' => $queryParams,
            ]);

            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->get($url, $queryParams);

            Log::info('AI Calendar Month API Response', [
                'status' => $response->status(),
                'user_id' => $userId,
                'response' => $response->json(),
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            }

            Log::warning('AI Calendar Month API Unsuccessful Response, using local fallback', [
                'status' => $response->status(),
                'user_id' => $userId,
                'body' => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::error('AI Calendar Month API Connection Error, using local fallback', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        // Local fallback: fetch inputs from database
        $inputs = CycleCalendarInput::where('user_id', $userId)
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($input) {
                $formatDate = function ($dateVal) {
                    if (! $dateVal || $dateVal === '0000-00-00' || $dateVal === '0000-00-00 00:00:00') {
                        return null;
                    }
                    if ($dateVal instanceof \DateTimeInterface) {
                        return $dateVal->format('Y-m-d');
                    }
                    return \Carbon\Carbon::parse($dateVal)->format('Y-m-d');
                };

                return [
                    'id' => $input->id,
                    'user_id' => $input->user_id,
                    'start_date' => $formatDate($input->start_date),
                    'end_date' => $formatDate($input->end_date),
                    'is_day_n' => (bool) $input->is_day_n,
                    'created_at' => $input->created_at,
                    'updated_at' => $input->updated_at,
                ];
            })->values();

        $phases = \App\Services\CycleCalculatorService::calculatePhases(
            $cycleLength,
            $lutealLength,
            $userSettings['period_length']
        );

        return response()->json([
            'success' => true,
            'data' => $inputs,
            'cycle_settings' => $userSettings,
            'calculated_phases' => $phases,
            'message' => 'Calendar month fetched from local database fallback.',
            'ai_fallback' => true,
        ]);
    }

    /**
     * Get next period prediction from AI Engine.
     *
     * AI endpoint:
     * GET /api/v1/cycle-engine/calendar/next-period?user_id={user_id}
     */
    public function syncNextPeriod(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $url = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/')
            . '/api/v1/cycle-engine/calendar/next-period';

        $userId = $request->query('user_id', $user->id);
        $userSettings = \App\Services\CycleCalculatorService::getUserCycleSettings($userId);
        $cycleLength = $userSettings['cycle_length'];
        $lutealLength = $userSettings['luteal_phase_length'];

        $queryParams = array_merge([
            'cycle_length' => $cycleLength,
            'average_cycle_length' => $cycleLength,
            'luteal_phase_length' => $lutealLength,
        ], $request->query(), ['user_id' => $userId]);

        try {
            Log::info('Calling AI Calendar Next Period API', [
                'url' => $url,
                'user_id' => $userId,
                'query' => $queryParams,
            ]);

            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->get($url, $queryParams);

            Log::info('AI Calendar Next Period API Response', [
                'status' => $response->status(),
                'user_id' => $userId,
                'response' => $response->json(),
            ]);

            if ($response->successful()) {
                $result = $response->json();

                if (! empty($result['predicted_date'])) {
                    CycleStatistic::updateOrCreate(
                        ['user_id' => $userId],
                        [
                            'predicted_next_period' => $result['predicted_date'],
                            'average_cycle_length'  => $cycleLength,
                        ]
                    );
                }

                return response()->json([
                    'success' => true,
                    'data' => $result,
                ]);
            }

            Log::warning('AI Calendar Next Period API Unsuccessful Response, using local fallback', [
                'status' => $response->status(),
                'user_id' => $userId,
                'body' => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::error('AI Calendar Next Period API Connection Error, using local fallback', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        // Local fallback calculation for next period using user's specific cycle length
        $latestInput = CycleCalendarInput::where('user_id', $userId)->latest('start_date')->first();
        $predictedDate = null;

        if ($latestInput && $latestInput->start_date) {
            $startDate = \Carbon\Carbon::parse($latestInput->start_date);
            $predictedDate = $startDate->addDays($cycleLength)->toDateString();

            CycleStatistic::updateOrCreate(
                ['user_id' => $userId],
                [
                    'predicted_next_period' => $predictedDate,
                    'average_cycle_length'  => $cycleLength,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'predicted_date' => $predictedDate,
                'cycle_length' => $cycleLength,
                'status' => $predictedDate ? 'calculated_local_fallback' : 'empty',
            ],
            'message' => 'Next period prediction calculated from local fallback.',
            'ai_fallback' => true,
        ]);
    }
}