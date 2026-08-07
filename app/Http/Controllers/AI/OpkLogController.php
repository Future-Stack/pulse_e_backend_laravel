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
        $userId = auth()->id() ?? $request->query('user_id'); 

        if (!$userId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated user.'
            ], 401);
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

        try {
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->get("{$baseUrl}/api/v1/cycle-engine/opk/ui", [
                    'user_id' => $userId
                ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }

            return response()->json([
                'status'     => 'error',
                'message'    => 'Failed to fetch data from OPK API',
                'api_status' => $response->status(),
                'error'      => $response->json()
            ], $response->status());

        } catch (ConnectionException $e) {
            Log::error("OPK API Connection Timeout (getOpkUiData): " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'The AI server took too long to respond. Please try again later.'
            ], 504);

        } catch (\Throwable $e) {
            Log::error("OPK API Error (getOpkUiData): " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status'      => 'error',
                'message'     => 'Something went wrong while processing your request.',
                'debug_error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
        $userId = auth()->id() ?? $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated user.'], 401);
        }

        $cardsData = $request->input('cards', []);

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

        try {
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 2000, function ($exception) {
                    return $exception instanceof ConnectionException;
                })
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/api/v1/cycle-engine/opk/ui?user_id={$userId}", [
                    'cards' => $cardsData
                ]);

            if ($response->successful()) {
                $apiData = $response->json();

                $opkRecord = OpkData::create([
                    'user_id'       => $userId,
                    'response_data' => is_string($apiData) ? json_decode($apiData, true) : $apiData,
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'API response stored successfully!',
                    'data'    => $opkRecord
                ], 200);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch data from API',
                'error'   => $response->json()
            ], $response->status());

        } catch (ConnectionException $e) {
            Log::error("OPK API Connection Timeout (storeOpkUiData): " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Connection timed out while reaching the AI service. Please try again.'
            ], 504);

        } catch (\Throwable $e) {
            Log::error("OPK API Error (storeOpkUiData): " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status'      => 'error',
                'message'     => 'Something went wrong while processing your request.',
                'debug_error' => config('app.debug') ? $e->getMessage() : null,
                'line'        => config('app.debug') ? $e->getLine() : null
            ], 500);
        }
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

        OpkLog::updateOrCreate(
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

        try {
            // AI Engine expects OPK UI endpoint with user_id
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->post("{$baseUrl}/api/v1/cycle-engine/opk/ui?user_id={$userId}", array_merge($request->all(), [
                    'cards'    => $request->input('cards', []),
                    'log_date' => $logDate,
                    'result'   => $result,
                ]));

            // Fallback GET to fetch latest OPK UI structure if POST returns empty or specific format
            $aiData = $response->successful() ? $response->json() : null;
            if (!$aiData) {
                $getRes = Http::timeout(30)->get("{$baseUrl}/api/v1/cycle-engine/opk/ui", ['user_id' => $userId]);
                if ($getRes->successful()) {
                    $aiData = $getRes->json();
                }
            }

            // Auto-trigger cycle summary sync so SignalHistory, MenstrualCycle and OvulationReconciliation refresh
            try {
                app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
            } catch (\Throwable $e) {
                Log::warning("OPK auto-sync warning: " . $e->getMessage());
            }

            $responseData = array_merge(is_array($aiData) ? $aiData : [], [
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
        $userId = auth()->id() ?? $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated user.'], 401);
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

        try {
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->get("{$baseUrl}/api/v1/cycle-engine/opk/testing-window", [
                    'user_id' => $userId
                ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch testing window from AI service',
                'error'   => $response->json()
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error("OPK API Error (testingWindow): " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while fetching testing window.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function todayStatus(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated user.'], 401);
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');

        try {
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->get("{$baseUrl}/api/v1/cycle-engine/opk/today-status", [
                    'user_id' => $userId
                ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch today status from AI service',
                'error'   => $response->json()
            ], $response->status());
        } catch (\Throwable $e) {
            Log::error("OPK API Error (todayStatus): " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while fetching today status.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}