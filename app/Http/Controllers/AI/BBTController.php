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

            $response = Http::timeout(90)->post('https://ai.fightthenumber.com/api/v1/cycle-engine/bbt/ui?user_id=' . $userId, [
                'temperature_f' => $validated['temperature_f'],
                'flags' => $validated['flags'] ?? [],
            ]);

            $data = $response->json();

            if ($response->successful()) {
                foreach ($data['bbt_chart']['points'] as $point) {
                    BbtLog::updateOrCreate(
                        [
                            'log_date' => $point['date'],
                        ],
                        [
                            'user_id'     => $userId,
                            'temperature' => $point['temperature_f'],
                            'unit'        => 'F',
                            'logged_at'   => now(),
                            'is_excluded' => $point['is_excluded'] ?? false,
                            'illness'     => in_array('illness', $point['flags'] ?? []),
                            'poor_sleep'  => in_array('poor_sleep', $point['flags'] ?? []),
                            'alcohol'     => in_array('alcohol', $point['flags'] ?? []),
                            'late_wakeup' => in_array('late_wakeup', $point['flags'] ?? []),
                            'travel'      => in_array('travel', $point['flags'] ?? []),
                            'notes'       => null,

                            'coverline_value'    => $data['bbt_chart']['coverline_value'] ?? null,
                            'ovulation_confirmed'=> ($data['coverline_algorithm']['summary']['coverline'] !== '—'),
                            'cycle_day'          => $point['day'] ?? null,
                            'phase'              => $data['coverline_algorithm']['summary']['phase'] ?? null,
                        ]
                    );
                }
            }


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

            $response = Http::timeout(90)->get('https://ai.fightthenumber.com/api/v1/cycle-engine/bbt/ui',
                [
                    'user_id' => $userId
                ]);


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

    public function fetchBbtLogs(Request $request)
    {
        try {
            // Fetch all logs for the authenticated user
            $logs = BbtLog::where('user_id', auth()->id())
                ->orderBy('log_date')
                ->get();

            // Group logs by date
            $grouped = $logs->groupBy('log_date');

            return response()->json([
                'success' => true,
                'data'    => $grouped,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Fetching BBT logs failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


}
