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
                'date' => 'required|date',
                'temperature_f' => 'required|numeric',
                'time' => 'required|string',
                'flags' => 'nullable|array',
            ]);

              $response['data'] = Http::post(env('AI_SERVICE_URL') . '/api/v1/cycle-engine/bbt/log', [
                'date' => $validated['date'],
                'temperature_f' => $validated['temperature_f'],
                'time' => $validated['time'],
                'flags' => $validated['flags'] ?? [],
            ]);

            $log = $response['data']['log'];

            $bbtLog = BbtLog::create([
                'cycle_id'   => $response['data']['reconciliation']['cycle_id'], // adjust if cycle_id is numeric FK
                'user_id'    => $response['data']['reconciliation']['user_id'],
                'log_date'   => $log['date'],
                'temperature'=> $log['temperature_f'],
                'unit'       => 'F',
                'logged_at'  => $log['logged_at_time'], // "08:01" string maps to TIME column
                'illness'    => in_array('illness', $log['flags']),
                'poor_sleep' => in_array('poor_sleep', $log['flags']),
                'alcohol'    => in_array('alcohol', $log['flags']),
                'late_wakeup'=> in_array('late_wakeup', $log['flags']),
                'travel'     => in_array('travel', $log['flags']),
                'is_excluded'=> false,
                'notes'      => null,
            ]);

            if ($response['data']->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'BBT data logged successfully.',
                    'data' => $response['data']->json(),
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to log BBT data.',
                'error' => $response['data']->body(),
            ], $response['data']->status());

        } catch (\Exception $e) {
            \Log::error('BBT log failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
