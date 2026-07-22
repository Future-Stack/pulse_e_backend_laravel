<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\FetchSmartAnalysisJob;
use App\Models\SmartAnalysis;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SmartAnalysisController extends Controller
{
    public function show(int $userId): JsonResponse
    {

        // User check
        if (! User::where('id', $userId)->exists()) {

            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }



        // Today's smart analysis check
        $smartAnalysis = SmartAnalysis::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->latest()
            ->first();



        /**
         * Already completed
         */
        if (
            $smartAnalysis &&
            $smartAnalysis->status === 'completed'
        ) {

            return response()->json([
                'success' => true,
                'message' => 'Smart analysis fetched successfully.',
                'data' => $smartAnalysis,
            ]);
        }



        /**
         * Currently generating
         */
        if (
            $smartAnalysis &&
            in_array($smartAnalysis->status, [
                'pending',
                'processing'
            ])
        ) {

            return response()->json([
                'success' => true,
                'message' => 'Smart analysis is being generated.',
                'data' => [
                    'id' => $smartAnalysis->id,
                    'status' => $smartAnalysis->status,
                ],
            ], 202);
        }



        /**
         * Failed - retry same record
         */
        if (
            $smartAnalysis &&
            $smartAnalysis->status === 'failed'
        ) {

            $smartAnalysis->update([
                'status' => 'pending'
            ]);


            FetchSmartAnalysisJob::dispatch(
                $smartAnalysis->id
            );


            return response()->json([
                'success' => true,
                'message' => 'Smart analysis regeneration started.',
                'data' => [
                    'id' => $smartAnalysis->id,
                    'status' => 'pending',
                ],
            ], 202);
        }




        /**
         * First time generate
         */
        $smartAnalysis = SmartAnalysis::create([
            'user_id' => $userId,
            'status' => 'pending',
        ]);



        FetchSmartAnalysisJob::dispatch(
            $smartAnalysis->id
        );



        return response()->json([
            'success' => true,
            'message' => 'Smart analysis generation started.',
            'data' => [
                'id' => $smartAnalysis->id,
                'status' => 'pending',
            ],
        ], 202);
    }
}