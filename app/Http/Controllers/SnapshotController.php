<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class SnapshotController extends Controller
{
    /**
     * Health Snapshot
     */
    public function snapshot($userId): JsonResponse
    {
        $user = User::with([
            'terraActivities',
            'healthLogs',
            'skinAnalyses',
        ])->find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $terra = $user->terraActivities()->latest()->first();
        $health = $user->healthLogs()->latest()->first();
        $skin = $user->skinAnalyses()->latest()->first();

        $payload = $terra?->payload ?? [];

        return response()->json([
            'success' => true,
            'message' => 'Health snapshot retrieved successfully.',
            'data' => [

                // Terra Activity
                'sleep' => [
                    'title'  => 'Sleep',
                    'value'  => $payload['sleep']['hours'] ?? null,
                    'status' => $payload['sleep']['quality'] ?? null,
                ],

                // Health Log
                'energy' => [
                    'title'  => 'Energy',
                    'value'  => $health?->energy_level,
                    'status' => optional($health?->log_date)->format('d M Y'),
                ],

                // Terra Activity
                'hrv' => [
                    'title'  => 'HRV',
                    'value'  => $payload['hrv']['value'] ?? null,
                    'status' => $payload['hrv']['status'] ?? null,
                ],

                // Terra Activity
                'stress' => [
                    'title'  => 'Stress',
                    'value'  => $payload['stress']['level'] ?? null,
                    'status' => $payload['stress']['status'] ?? null,
                ],

                // Skin Scan
                'readiness' => [
                    'title'  => 'Readiness',
                    'value'  => $skin?->overall_score,
                    'status' => 'Excellent',
                ],

                // Skin Scan
                'skin' => [
                    'title'  => 'Skin',
                    'value'  => $skin?->hydration_status,
                    'status' => $skin?->neumera_insight,
                ],

            ],
        ], 200);
    }
}