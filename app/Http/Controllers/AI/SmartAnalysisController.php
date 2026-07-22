<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\FetchSmartAnalysisJob;
use App\Models\SmartAnalysis;
use Illuminate\Http\JsonResponse;

class SmartAnalysisController extends Controller
{
    public function show(int $userId): JsonResponse
    {
        $smartAnalysis = SmartAnalysis::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->latest()
            ->first();

        // যদি আজকের completed data থাকে
        if ($smartAnalysis && $smartAnalysis->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'Smart analysis fetched successfully.',
                'data' => $smartAnalysis,
            ]);
        }

        // যদি processing/pending থাকে
        if ($smartAnalysis && in_array($smartAnalysis->status, [
            'pending',
            'processing',
        ])) {
            return response()->json([
                'success' => true,
                'message' => 'Smart analysis is being generated.',
                'status' => $smartAnalysis->status,
            ], 202);
        }

        // নতুন record তৈরি
        $smartAnalysis = SmartAnalysis::create([
            'user_id' => $userId,
            'status' => 'pending',
        ]);

        // Queue Job Dispatch
        FetchSmartAnalysisJob::dispatch($smartAnalysis->id);

        return response()->json([
            'success' => true,
            'message' => 'Smart analysis generation started.',
            'status' => 'pending',
        ], 202);
    }
}