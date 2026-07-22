<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\FetchHealthTrendJob;
use App\Models\HealthTrend;
use Illuminate\Http\JsonResponse;

class HealthTrendController extends Controller
{
    public function show(int $userId): JsonResponse
    {
        //  dd($userId);
        $healthTrend = HealthTrend::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->latest()
            ->first();


        if ($healthTrend && $healthTrend->status === 'completed') {

            return response()->json([
                'success' => true,
                'message' => 'Health trends fetched successfully.',
                'data' => $healthTrend
            ]);
        }


        if ($healthTrend && in_array($healthTrend->status, [
            'pending',
            'processing'
        ])) {

            return response()->json([
                'success' => true,
                'message' => 'Health trends are being generated.',
                'status' => $healthTrend->status
            ], 202);
        }


        $healthTrend = HealthTrend::create([
            'user_id' => $userId,
            'status' => 'pending'
        ]);


        FetchHealthTrendJob::dispatch($healthTrend->id);


        return response()->json([
            'success' => true,
            'message' => 'Health trends generation started.',
            'status' => 'pending'
        ], 202);
    }
}