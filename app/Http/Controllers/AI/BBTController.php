<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\BbtLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BBTController extends Controller
{
    public function logBbtData(Request $request)
    {
        try {
            $validated = $request->validate([
                'temperature_f' => 'required|numeric',
                'flags' => 'nullable|array',
            ]);

            $userId = auth()->id();

            $response = Http::post(env('AI_SERVICE_URL') . '/api/v1/cycle-engine/bbt/ui?user_id=' . $userId, [
                'temperature_f' => $validated['temperature_f'],
                'flags' => $validated['flags'] ?? [],
            ]);

//            $log = $response['data']['log'];

            if ($response) {
                return response()->json([
                    'success' => true,
                    'message' => 'BBT data logged successfully.',
                    'data' => $response->json(),
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to log BBT data.',
                'error' => 'Error logging BBT data.',
            ]);

        } catch (\Exception $e) {
            \Log::error('BBT log failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function fetchLog(Request $request)
    {
        try {
            $userId = auth()->id();

            $response = Http::get(
                'https://female-mood-analyzer.onrender.com/api/v1/cycle-engine/bbt/ui',
                [
                    'user_id' => $userId
                ]
            );

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'API returned error',
                'data' => $response->json(),
            ], $response->status());

        } catch (\Exception $e) {
            \Log::error('Cycle Engine API failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
