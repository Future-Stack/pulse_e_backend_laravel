<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BBTController extends Controller
{
    public function logBbtData(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'temperature_f' => 'required|numeric',
                'time' => 'required|string',
                'flags' => 'nullable|array',
            ]);

            $response = Http::post(env('AI_SERVICE_URL') . '/api/v1/cycle-engine/bbt/log', [
                'date' => $validated['date'],
                'temperature_f' => $validated['temperature_f'],
                'time' => $validated['time'],
                'flags' => $validated['flags'] ?? [],
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'BBT data logged successfully.',
                    'data' => $response->json(),
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to log BBT data.',
                'error' => $response->body(),
            ], $response->status());

        } catch (\Exception $e) {
            \Log::error('BBT log failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
