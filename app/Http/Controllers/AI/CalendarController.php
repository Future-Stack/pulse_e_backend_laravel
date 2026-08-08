<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
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
        $queryParams = array_merge($request->query(), ['user_id' => $userId]);

        try {
            Log::info('Calling AI Calendar Month API', [
                'url' => $url,
                'user_id' => $userId,
                'query' => $queryParams,
            ]);

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($url, $queryParams);

            Log::info('AI Calendar Month API Response', [
                'status' => $response->status(),
                'user_id' => $userId,
                'response' => $response->json(),
            ]);

        } catch (\Throwable $e) {
            Log::error('AI Calendar Month API Connection Error', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to AI Engine.',
                'error' => $e->getMessage(),
            ], 500);
        }

        if (! $response->successful()) {
            Log::warning('AI Calendar Month API Unsuccessful Response', [
                'status' => $response->status(),
                'user_id' => $userId,
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch calendar month from AI Engine.',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        }

        $data = $response->json();

        return response()->json([
            'success' => true,
            'data' => $data,
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
        $queryParams = array_merge($request->query(), ['user_id' => $userId]);

        try {
            Log::info('Calling AI Calendar Next Period API', [
                'url' => $url,
                'user_id' => $userId,
                'query' => $queryParams,
            ]);

            $response = Http::timeout(120)
                ->acceptJson()
                ->get($url, $queryParams);

            Log::info('AI Calendar Next Period API Response', [
                'status' => $response->status(),
                'user_id' => $userId,
                'response' => $response->json(),
            ]);

        } catch (\Throwable $e) {
            Log::error('AI Calendar Next Period API Connection Error', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to AI Engine.',
                'error' => $e->getMessage(),
            ], 500);
        }

        if (! $response->successful()) {
            Log::warning('AI Calendar Next Period API Unsuccessful Response', [
                'status' => $response->status(),
                'user_id' => $userId,
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch next period prediction.',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        }

        $result = $response->json();

        /*
        |--------------------------------------------------------------------------
        | Save predicted next period date
        |--------------------------------------------------------------------------
        */

        if (! empty($result['predicted_date'])) {
            \App\Models\CycleStatistic::updateOrCreate(
                ['user_id' => $userId],
                ['predicted_next_period' => $result['predicted_date']]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}