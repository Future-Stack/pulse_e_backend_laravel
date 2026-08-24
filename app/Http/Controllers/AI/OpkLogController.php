<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\OpkLog;
use App\Models\MenstrualCycle;
use App\Models\OpkData;
use App\Models\User;
use App\Services\OpkReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Carbon\Carbon;

class OpkLogController extends Controller
{
    
    public function getOpkUiData(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? $request->query('user_id') ?? $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated user.'
            ], 401);
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->get("{$baseUrl}/api/v1/cycle-engine/opk/ui", [
                    'user_id' => $userId
                ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }
        } catch (\Throwable $e) {
            Log::warning("OPK API Warning (getOpkUiData): " . $e->getMessage());
        }

        return response()->json($this->buildDynamicOpkUiPayload($userId), 200);
    }

    
    public function getStoredOpkData(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User ID is required'
            ], 400);
        }

        $opkRecord = OpkData::where('user_id', $userId)->latest()->first();

        if (!$opkRecord) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No OPK data found for this user.'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Latest data retrieved successfully from database!',
            'data'    => $opkRecord->response_data
        ], 200);
    }

    
    public function storeOpkUiData(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated user.'], 401);
        }

        $cardsData = $request->input('cards', []);
        $logDate = $request->input('log_date', today()->toDateString());
        $result = $request->input('result', 'positive');

        // 1. ALWAYS Save OpkLog to DB first
        $cycle = MenstrualCycle::where('user_id', $userId)
            ->where('is_completed', false)
            ->latest('period_start_date')
            ->first();

        if (!$cycle) {
            $cycle = MenstrualCycle::create([
                'user_id' => $userId,
                'period_start_date' => $logDate,
                'is_completed' => false,
                'prediction_source' => 'opk',
            ]);
        }

        $opkLog = OpkLog::updateOrCreate(
            [
                'cycle_id' => $cycle->id,
                'log_date' => $logDate,
            ],
            [
                'result' => $result,
                'lh_value' => $request->input('lh_value'),
                'outside_window' => $request->input('outside_window', false),
                'affects_prediction' => $request->input('affects_prediction', true),
                'note' => $request->input('note'),
            ]
        );

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $apiData = null;

        // 2. Call AI Engine for OPK UI (POST -> GET -> Dynamic UI Fallback)
        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->post("{$baseUrl}/api/v1/cycle-engine/opk/ui?user_id={$userId}", [
                    'cards' => $cardsData
                ]);

            if ($response->successful()) {
                $apiData = $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning("OPK AI POST service call warning: " . $e->getMessage());
        }

        if (! is_array($apiData) || empty($apiData['testing_window'])) {
            try {
                $getRes = Http::timeout(5)->connectTimeout(2)->acceptJson()->get("{$baseUrl}/api/v1/cycle-engine/opk/ui", ['user_id' => $userId]);
                if ($getRes->successful()) {
                    $apiData = $getRes->json();
                }
            } catch (\Throwable $e) {
                Log::warning("OPK AI GET service call warning: " . $e->getMessage());
            }
        }

        if (! is_array($apiData) || empty($apiData['testing_window'])) {
            $apiData = $this->buildDynamicOpkUiPayload($userId, $cycle, $result);
        }

        // 3. Auto-trigger cycle summary sync (updates SignalHistory & OvulationReconciliation)
        try {
            app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
        } catch (\Throwable $e) {
            Log::warning("OPK auto-sync warning: " . $e->getMessage());
        }

        $opkRecord = OpkData::create([
            'user_id'       => $userId,
            'response_data' => $apiData,
        ]);

        return response()->json(array_merge(is_array($apiData) ? $apiData : [], [
            'success' => true,
            'status'  => 'success',
            'message' => 'OPK data stored successfully!',
            'opk_log' => $opkLog,
            'data'    => $opkRecord
        ]), 200);
    }

    
    public function getOpkDataHistory(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User ID is required'
            ], 400);
        }

        $query = OpkData::where('user_id', $userId);

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('filter')) {
            switch ($request->filter) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;

                case 'week':
                    $query->where('created_at', '>=', Carbon::now()->subDays(7));
                    break;

                case 'month':
                    $query->where('created_at', '>=', Carbon::now()->subDays(30));
                    break;

                case 'last_3_months':
                    $query->where('created_at', '>=', Carbon::now()->subDays(90));
                    break;
            }
        }

        $opkRecords = $query->latest()->get();

        if ($opkRecords->isEmpty()) {
            return response()->json([
                'status'  => 'success',
                'message' => 'No OPK data found for the selected timeframe.',
                'count'   => 0,
                'data'    => []
            ], 200);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'OPK history data retrieved successfully!',
            'count'   => $opkRecords->count(),
            'data'    => $opkRecords->map(function ($record) {
                return [
                    'id'            => $record->id,
                    'created_at'    => $record->created_at->toDateTimeString(),
                    'date'          => $record->created_at->format('Y-m-d'),
                    'response_data' => $record->response_data,
                ];
            })
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id() ?? $request->input('user_id');

            if (!$userId) {
                return response()->json(['message' => 'Unauthenticated user.'], 401);
            }

            $logDate = $request->input('log_date', now()->toDateString());
            $result = $request->input('result', 'positive');

            $cycle = MenstrualCycle::where('user_id', $userId)
                ->where('is_completed', false)
                ->latest('period_start_date')
                ->first();

            if (!$cycle) {
                $cycle = MenstrualCycle::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'is_completed' => false,
                    ],
                    [
                        'period_start_date' => $logDate,
                        'prediction_source' => 'opk',
                    ]
                );
            }

            $opkLog = OpkLog::updateOrCreate(
                [
                    'cycle_id' => $cycle->id,
                    'log_date' => $logDate,
                ],
                [
                    'result' => $result,
                    'lh_value' => $request->input('lh_value'),
                    'outside_window' => $request->input('outside_window', false),
                    'affects_prediction' => $request->input('affects_prediction', true),
                    'note' => $request->input('note'),
                ]
            );

            $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
            $aiData = null;

            try {
                $response = Http::timeout(5)
                    ->connectTimeout(2)
                    ->acceptJson()
                    ->post("{$baseUrl}/api/v1/cycle-engine/opk/ui?user_id={$userId}", array_merge($request->all(), [
                        'cards'    => $request->input('cards', []),
                        'log_date' => $logDate,
                        'result'   => $result,
                    ]));

                if ($response->successful()) {
                    $aiData = $response->json();
                }
            } catch (\Throwable $e) {
                Log::warning("OPK AI POST service call warning: " . $e->getMessage());
            }

            if (! is_array($aiData) || empty($apiData['testing_window'])) {
                try {
                    $getRes = Http::timeout(5)->connectTimeout(2)->acceptJson()->get("{$baseUrl}/api/v1/cycle-engine/opk/ui", ['user_id' => $userId]);
                    if ($getRes->successful()) {
                        $aiData = $getRes->json();
                    }
                } catch (\Throwable $e) {
                    Log::warning("OPK AI GET service call warning: " . $e->getMessage());
                }
            }

            if (! is_array($aiData) || empty($aiData['testing_window'])) {
                $aiData = $this->buildDynamicOpkUiPayload($userId, $cycle, $result);
            }

            // Auto-trigger cycle summary sync so SignalHistory, MenstrualCycle and OvulationReconciliation refresh
            try {
                app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
            } catch (\Throwable $e) {
                Log::warning("OPK auto-sync warning: " . $e->getMessage());
            }

            if (is_array($aiData) && ! empty($aiData)) {
                OpkData::create([
                    'user_id'       => $userId,
                    'response_data' => $aiData,
                ]);
            }

            $responseData = array_merge(is_array($aiData) ? $aiData : [], [
                'success' => true,
                'status'  => 'success',
                'message' => 'OPK test logged successfully.',
                'opk_log' => OpkLog::where('cycle_id', $cycle->id)->where('log_date', $logDate)->first(),
                'ai_data' => $aiData,
            ]);

            return response()->json($responseData, 200);

        } catch (\Throwable $e) {
            Log::error("OPK API Error (store): " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while processing your request.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function testingWindow(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? $request->query('user_id') ?? $request->input('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated user.'], 401);
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->get("{$baseUrl}/api/v1/cycle-engine/opk/testing-window", [
                    'user_id' => $userId
                ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }
        } catch (\Throwable $e) {
            Log::warning("OPK API Warning (testingWindow): " . $e->getMessage());
        }

        $fullPayload = $this->buildDynamicOpkUiPayload($userId);
        return response()->json($fullPayload['testing_window'] ?? [], 200);
    }

    public function todayStatus(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? $request->query('user_id') ?? $request->input('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated user.'], 401);
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->get("{$baseUrl}/api/v1/cycle-engine/opk/today-status", [
                    'user_id' => $userId
                ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }
        } catch (\Throwable $e) {
            Log::warning("OPK API Warning (todayStatus): " . $e->getMessage());
        }

        $fullPayload = $this->buildDynamicOpkUiPayload($userId);
        return response()->json($fullPayload['log_todays_test'] ?? [], 200);
    }

    protected function buildDynamicOpkUiPayload($userId, $cycle = null, $todayResult = 'negative'): array
    {
        if (! $cycle) {
            $cycle = MenstrualCycle::where('user_id', $userId)
                ->where('is_completed', false)
                ->latest('period_start_date')
                ->first();
        }

        $periodStartDate = $cycle?->period_start_date ? Carbon::parse($cycle->period_start_date) : today();
        $currentCycleDay = max(1, (int) $periodStartDate->diffInDays(today()) + 1);

        $opkLogs = OpkLog::where('cycle_id', $cycle?->id ?? 0)->get();
        $peakLog = $opkLogs->whereIn('result', ['peak', 'positive'])->first();

        $surgeDetected = (bool) $peakLog;
        $lhSurgeDay = $peakLog ? max(1, (int) $periodStartDate->diffInDays(Carbon::parse($peakLog->log_date)) + 1) : null;

        $bbtLog = \App\Models\BbtLog::where('cycle_id', $cycle?->id ?? 0)
            ->where(function($q) {
                $q->where('ovulation_confirmed', true)->orWhereNotNull('cycle_day');
            })->first();

        $bbtConfirmedDay = $bbtLog?->cycle_day ?? ($bbtLog && $cycle?->period_start_date ? max(1, (int) $periodStartDate->diffInDays(Carbon::parse($bbtLog->log_date)) + 1) : null);

        $latestInput = \App\Models\CycleCalendarInput::where('user_id', $userId)->latest()->first();
        $todayMucus = $latestInput?->cervical_mucus ?? 'dry';

        $cards = [];
        $windowStart = 10;
        $windowEnd = 15;
        for ($d = $windowStart; $d <= $windowEnd; $d++) {
            $matchingLog = $opkLogs->first(function ($l) use ($periodStartDate, $d) {
                return (int) $periodStartDate->diffInDays(Carbon::parse($l->log_date)) + 1 === $d;
            });

            $status = $d < $currentCycleDay ? 'completed' : ($d === $currentCycleDay ? 'active' : 'upcoming');
            $note = $d === 10 ? 'Window opens' : ($d === 14 ? 'Predicted peak' : ($d === 15 ? 'Window closes' : null));

            $cards[] = [
                'cycle_day' => $d,
                'label'     => "Day {$d}",
                'status'    => $status,
                'result'    => $matchingLog?->result ?? null,
                'note'      => $note,
            ];
        }

        $testingWindowStatus = ($currentCycleDay >= $windowStart && $currentCycleDay <= $windowEnd) ? 'open' : ($currentCycleDay > $windowEnd ? 'closed' : 'not_open');

        $todayLog = $opkLogs->firstWhere('log_date', today()->toDateString());
        $alreadyLogged = (bool) $todayLog;
        $loggedResult = $todayLog?->result ?? $todayResult;

        return [
            'info_alert' => [
                'title'   => "OPK vs BBT: What's the Difference?",
                'message' => "Ovulation Predictor Kits (OPKs) detect the LH surge in your urine, which typically happens 12–36 hours before ovulation — giving you a heads-up that your fertile peak is near. Basal Body Temperature (BBT), on the other hand, rises slightly after ovulation has occurred, confirming that it happened. Together, OPK predicts and BBT confirms.",
            ],
            'testing_window' => [
                'title'    => 'Your Fertile Testing Window',
                'subtitle' => "Cycle days {$windowStart}–{$windowEnd} are your predicted OPK testing window",
                'status'   => $testingWindowStatus,
                'cards'    => $cards,
                'summary'  => [
                    'window'        => "Cycle days {$windowStart}–{$windowEnd}",
                    'opk_peak'      => $lhSurgeDay ? "Day {$lhSurgeDay}" : 'Not yet detected',
                    'bbt_confirmed' => $bbtConfirmedDay ? "Day {$bbtConfirmedDay}" : 'Not yet confirmed',
                ],
            ],
            'lh_surge_detection' => [
                'detected'          => $surgeDetected,
                'title'             => $surgeDetected ? "LH Surge Detected on Day {$lhSurgeDay}" : 'LH Surge Not Yet Detected',
                'message'           => $surgeDetected
                    ? "Your peak LH surge was logged on cycle day {$lhSurgeDay}."
                    : "You're currently on cycle day {$currentCycleDay}. Your fertile testing window is predicted for cycle days {$windowStart}–{$windowEnd}.",
                'surge_day'         => $lhSurgeDay,
                'bbt_confirmed_day' => $bbtConfirmedDay,
            ],
            'log_todays_test' => [
                'title'          => "Log Today's OPK Test",
                'guidance'       => "You're on cycle day {$currentCycleDay} of your cycle.",
                'current_day'    => $currentCycleDay,
                'already_logged' => $alreadyLogged,
                'logged_result'  => $loggedResult,
                'options'        => [
                    ['value' => 'negative', 'label' => 'Negative', 'description' => 'LH not elevated'],
                    ['value' => 'low', 'label' => 'Low', 'description' => 'Faint test line'],
                    ['value' => 'high', 'label' => 'High', 'description' => 'Test line getting darker'],
                    ['value' => 'peak', 'label' => 'Peak / Positive', 'description' => 'Test line as dark or darker than control'],
                ],
            ],
            'cervical_mucus' => [
                'title'        => 'Cervical Mucus',
                'description'  => 'Cervical mucus changes throughout your cycle and is one of the most reliable natural signs of approaching ovulation.',
                'note'         => "Today you logged: {$todayMucus}",
                'today_logged' => $todayMucus,
                'options'      => [
                    ['value' => 'dry', 'label' => 'Dry', 'fertility' => 'low'],
                    ['value' => 'sticky', 'label' => 'Sticky', 'fertility' => 'low'],
                    ['value' => 'creamy', 'label' => 'Creamy', 'fertility' => 'medium'],
                    ['value' => 'watery', 'label' => 'Watery', 'fertility' => 'high'],
                    ['value' => 'egg_white', 'label' => 'Egg White', 'fertility' => 'peak'],
                ],
            ],
        ];
    }
}