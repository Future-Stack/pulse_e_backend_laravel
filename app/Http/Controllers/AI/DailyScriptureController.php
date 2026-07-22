<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\FetchDailyScriptureJob;
use App\Models\DailyScripture;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DailyScriptureController extends Controller
{
    /**
     * Get Daily Scripture by user id
     */
    public function show(int $userId): JsonResponse
    {
        // User check
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }


        // Today's scripture check
        $dailyScripture = DailyScripture::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->latest()
            ->first();


        /**
         * Already completed
         */
        if ($dailyScripture && $dailyScripture->status === 'completed') {

            return response()->json([
                'success' => true,
                'message' => 'Daily scripture fetched successfully.',
                'data' => $dailyScripture,
            ]);
        }


        /**
         * Currently generating
         */
        if ($dailyScripture && in_array($dailyScripture->status, [
            'pending',
            'processing',
        ])) {

            return response()->json([
                'success' => true,
                'message' => 'Daily scripture is being generated.',
                'data' => [
                    'id' => $dailyScripture->id,
                    'status' => $dailyScripture->status,
                ],
            ], 202);
        }


        /**
         * Retry failed generation
         */
        if ($dailyScripture && $dailyScripture->status === 'failed') {

            $dailyScripture->update([
                'status' => 'pending',
            ]);


            FetchDailyScriptureJob::dispatch($dailyScripture->id);


            return response()->json([
                'success' => true,
                'message' => 'Daily scripture regeneration started.',
                'data' => [
                    'id' => $dailyScripture->id,
                    'status' => 'pending',
                ],
            ], 202);
        }


        /**
         * Create new generation request
         */
        $dailyScripture = DailyScripture::create([
            'user_id' => $userId,
            'status' => 'pending',
        ]);


        FetchDailyScriptureJob::dispatch($dailyScripture->id);


        return response()->json([
            'success' => true,
            'message' => 'Daily scripture generation started.',
            'data' => [
                'id' => $dailyScripture->id,
                'status' => 'pending',
            ],
        ], 202);
    }
}