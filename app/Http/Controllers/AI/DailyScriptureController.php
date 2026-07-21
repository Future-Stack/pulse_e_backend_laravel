<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\FetchDailyScriptureJob;
use App\Models\DailyScripture;
use Illuminate\Http\JsonResponse;

class DailyScriptureController extends Controller
{
    /**
     * Get Daily Scripture by user id
     */
    public function show(int $userId): JsonResponse
    {
        // আজকের scripture বের করো
        $dailyScripture = DailyScripture::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->latest()
            ->first();

        // যদি আগে complete হয়ে থাকে
        if ($dailyScripture && $dailyScripture->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'Daily scripture fetched successfully.',
                'data'    => $dailyScripture->fresh(),
            ]);
        }

        // যদি এখনও pending বা processing থাকে
        if ($dailyScripture && in_array($dailyScripture->status, ['pending', 'processing'])) {
            return response()->json([
                'success' => true,
                'message' => 'Daily scripture is being generated.',
                'status'  => $dailyScripture->status,
            ], 202);
        }

        // নতুন record তৈরি করো
        $dailyScripture = DailyScripture::create([
            'user_id' => $userId,
            'status'  => 'pending',
        ]);

        // Queue Job dispatch করো
        FetchDailyScriptureJob::dispatch($dailyScripture->id);

        return response()->json([
            'success' => true,
            'message' => 'Daily scripture generation started.',
            'status'  => 'pending',
        ], 202);
    }
}
