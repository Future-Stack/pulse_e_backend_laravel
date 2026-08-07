<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class CalendarController extends Controller
{
    /**
     * Get calendar month from AI Engine.
     *
     * AI endpoint:
     * GET /api/v1/cycle-engine/calendar/month?user_id={user_id}
     */
   public function syncMonth()
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

    try {

        $response = Http::timeout(120)
            ->acceptJson()
            ->get($url, [
                'user_id' => $user->id,
            ]);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => 'Unable to connect to AI Engine.',
            'error' => $e->getMessage(),
        ], 500);
    }

    if (! $response->successful()) {

        return response()->json([
            'success' => false,
            'message' => 'Unable to fetch calendar month from AI Engine.',
            'status' => $response->status(),
            'error' => $response->json() ?? $response->body(),
        ], $response->status());
    }

    return response()->json(
        $response->json()
    );
}

    /**
     * Get next period prediction from AI Engine.
     *
     * AI endpoint:
     * GET /api/v1/cycle-engine/calendar/next-period?user_id={user_id}
     */
    public function syncNextPeriod()
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

    try {

        $response = Http::timeout(120)
            ->acceptJson()
            ->get($url, [
                'user_id' => $user->id,
            ]);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => 'Unable to connect to AI Engine.',
            'error' => $e->getMessage(),
        ], 500);
    }

    if (! $response->successful()) {

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

    if (!empty($result['predicted_date'])) {

        \App\Models\CycleStatistic::updateOrCreate(
            ['user_id' => $user->id],
            ['predicted_next_period' => $result['predicted_date']]
        );
    }

    return response()->json([
        'success' => true,
        'data' => $result,
    ]);
}
}