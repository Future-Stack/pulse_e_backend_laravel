<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\FetchHealthTrendJob;
use App\Models\HealthTrend;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class HealthTrendController extends Controller
{
    public function show(int $userId): JsonResponse
    {
        // User check
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }


        // Today's health trend check
        $healthTrend = HealthTrend::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->latest()
            ->first();



        /**
         * Already completed
         */
        if ($healthTrend && $healthTrend->status === 'completed') {

            return response()->json([
                'success' => true,
                'message' => 'Health trends fetched successfully.',
                'data' => $healthTrend->fresh(),
            ]);
        }



        /**
         * Currently generating
         */
        if ($healthTrend && in_array($healthTrend->status, [
            'pending',
            'processing'
        ])) {

            return response()->json([
                'success' => true,
                'message' => 'Health trends are being generated.',
                'data' => [
                    'id' => $healthTrend->id,
                    'status' => $healthTrend->status,
                ],
            ], 202);
        }



        /**
         * Retry failed generation
         */
        if ($healthTrend && $healthTrend->status === 'failed') {

            $healthTrend->update([
                'status' => 'pending',
            ]);


            FetchHealthTrendJob::dispatch($healthTrend->id);


            return response()->json([
                'success' => true,
                'message' => 'Health trends regeneration started.',
                'data' => [
                    'id' => $healthTrend->id,
                    'status' => 'pending',
                ],
            ], 202);
        }



        /**
         * First time generation
         */
        $healthTrend = HealthTrend::create([
            'user_id' => $userId,
            'status' => 'pending',
        ]);


        FetchHealthTrendJob::dispatch($healthTrend->id);



        return response()->json([
            'success' => true,
            'message' => 'Health trends generation started.',
            'data' => [
                'id' => $healthTrend->id,
                'status' => 'pending',
            ],
        ], 202);
    }
}