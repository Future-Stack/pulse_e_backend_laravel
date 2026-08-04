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
use Illuminate\Http\Client\RequestException;
use Carbon\Carbon;

class OpkLogController extends Controller
{
    
    public function getOpkUiData()
    {
        $userId = auth()->id(); 

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated user.'
            ], 401);
        }

        try {
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->get('https://ai.fightthenumber.com/api/v1/cycle-engine/opk/ui', [
                    'user_id' => $userId
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch data from OPK API',
                'api_status' => $response->status()
            ], $response->status());

        } catch (ConnectionException $e) {
            Log::error("OPK API Connection Timeout (getOpkUiData): " . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'The AI server took too long to respond. Please try again later.'
            ], 504); // 504 Gateway Timeout
        } catch (\Exception $e) {
            Log::error("OPK API Error (getOpkUiData): " . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred while connecting to the AI service.'
            ], 500);
        }
    }

    public function getStoredOpkData(Request $request)
    {
        $userId = auth()->id() ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'User ID is required'
            ], 400);
        }

        $opkRecord = OpkData::where('user_id', $userId)->latest()->first();

        if (!$opkRecord) {
            return response()->json([
                'status' => 'error',
                'message' => 'No OPK data found for this user.'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Latest data retrieved successfully from database!',
            'data'    => $opkRecord->response_data
        ], 200);
    }

    public function storeOpkUiData(Request $request)
    {
        $userId = auth()->id() ?? $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated user.'], 401);
        }

        $cardsData = $request->input('cards', []);

        try {
            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->retry(2, 2000, function ($exception) {
                    return $exception instanceof ConnectionException;
                })
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("https://ai.fightthenumber.com/api/v1/cycle-engine/opk/ui?user_id={$userId}", [
                    'cards' => $cardsData
                ]);

            if ($response->successful()) {
                $apiData = $response->json();

                $opkRecord = OpkData::create([
                    'user_id'       => $userId,
                    'response_data' => $apiData,
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

        } catch (\Exception $e) {
            Log::error("OPK API Error (storeOpkUiData): " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while processing your request.'
            ], 500);
        }
    }

    public function getOpkDataHistory(Request $request)
    {
        $userId = auth()->id() ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'User ID is required'
            ], 400);
        }

        $query = OpkData::where('user_id', $userId);

        if ($request->has('date') && !empty($request->date)) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->has('filter')) {
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

                default:
                    break;
            }
        }

        $opkRecords = $query->latest()->get();

        if ($opkRecords->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No OPK data found for the selected timeframe.',
                'count' => 0,
                'data' => []
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
}