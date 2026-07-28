<?php

namespace App\Http\Controllers\SkinScan;

use App\Http\Controllers\Controller;
use App\Models\SkinScan;
use App\Models\UserLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SkinScanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $history = SkinScan::where('user_id', $user->id)
            ->with('recommendations')
            ->orderBy('created_at', 'desc')
            ->get();

        $insightSummary = "Keep up your consistency to track changes over time!";

        if ($history->count() >= 2) {
            $latest = $history->first()->overall_score;
            $previous = $history->skip(1)->first()->overall_score;
            $diff = $latest - $previous;

            if ($diff > 0) {
                $insightSummary = "Your skin score has improved +{$diff} points over the past weeks. The biggest driver is improved sleep consistency. Keep it up!";
            } elseif ($diff < 0) {
                $insightSummary = "Your skin score dropped by " . abs($diff) . " points. Consider focusing on your hydration and sleep pattern today.";
            }
        }

        return response()->json([
            'success'         => true,
            'insight_summary' => $insightSummary,
            'data'            => $history,
        ], 200);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $scan = SkinScan::where('user_id', $request->user()->id)
            ->with('recommendations')
            ->find($id);

        if (!$scan) {
            return response()->json([
                'success' => false,
                'message' => 'Skin scan record not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $scan,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->has('scan') && $request->has('metrics')) {
            $request->merge(['scan' => $request->input('metrics')]);
        }

        try {
            $validated = $request->validate([
                'success'      => 'required|boolean',
                'session'      => 'nullable|boolean',
                'reason'       => 'nullable|string',
                'frame_count'  => 'nullable|integer',
                'frame_ids'    => 'nullable|array',
                'frame_ids.*'  => 'string',
                'content_type' => 'nullable|string',
                'image_path'   => 'nullable|string',

                'scan'                       => 'required|array',
                'scan.overall_score'         => 'required|numeric',
                'scan.hydration_score'       => 'required|numeric',
                'scan.hydration_status'      => 'required|string',
                'scan.redness_score'         => 'required|numeric',
                'scan.redness_status'        => 'required|string',
                'scan.texture_score'         => 'required|numeric',
                'scan.texture_status'        => 'required|string',
                'scan.glow_index'            => 'required|numeric',
                'scan.glow_status'           => 'required|string',
                'scan.pore_health_score'     => 'required|numeric',
                'scan.pore_health_status'    => 'required|string',
                'scan.elasticity_score'      => 'required|numeric',
                'scan.elasticity_status'     => 'required|string',
                'scan.neumera_insight'       => 'nullable|string',

                'recommendations'                       => 'nullable|array',
                'recommendations.*.icon_type'           => 'required_with:recommendations|string',
                'recommendations.*.recommendation_text' => 'required_with:recommendations|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Failed: Missing or invalid payload parameters.',
                'errors'  => $e->errors(),
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user. Authorization token missing or invalid.',
            ], 401);
        }

        $scan = $validated['scan'];

        // Core Scores Check
        $coreScores = [
            $scan['overall_score'],
            $scan['hydration_score'],
            $scan['redness_score'],
            $scan['texture_score'],
            $scan['glow_index'],
            $scan['pore_health_score'],
            $scan['elasticity_score'],
        ];
        $noFaceDetected = !$validated['success'] || array_sum($coreScores) === 0;

        if ($noFaceDetected) {
            return response()->json([
                'success' => false,
                'message' => $scan['neumera_insight']
                    ?? 'No skin was detected in the scan. Please retake the scan with your face clearly in view.',
                'code'    => 'NO_SKIN_DETECTED',
            ], 422);
        }

        // Quota Limit Check
        $userLimit = UserLimit::firstOrCreate(
            ['user_id' => $user->id],
            [
                'skin_scans_limit'        => 2,
                'ai_coaching_limit'       => 5,
                'deep_reports_limit'      => 0,
                'skin_scans_topup_limit'  => 0,
                'ai_coaching_topup_limit' => 0,
            ]
        );

        if ($userLimit->skin_scans_limit <= 0 && $userLimit->skin_scans_topup_limit <= 0) {
            $refreshDate = $userLimit->subscription_expires_at
                ? \Carbon\Carbon::parse($userLimit->subscription_expires_at)->format('M d, Y')
                : now()->addMonth()->startOfMonth()->format('M d, Y');

            return response()->json([
                'success' => false,
                'message' => "You've used your skin scans this month; they refresh on {$refreshDate}",
                'code'    => 'QUOTA_EXCEEDED',
            ], 403);
        }
        try {
            $skinScan = DB::transaction(function () use ($user, $validated, $scan, $userLimit) {
                $skinScan = SkinScan::create([
                    'user_id'            => $user->id,
                    'image_path'         => $validated['image_path'] ?? null,
                    'overall_score'      => (int) $scan['overall_score'],
                    'hydration_score'    => (int) $scan['hydration_score'],
                    'hydration_status'   => $scan['hydration_status'],
                    'redness_score'      => (int) $scan['redness_score'],
                    'redness_status'     => $scan['redness_status'],
                    'texture_score'      => (int) $scan['texture_score'],
                    'texture_status'     => $scan['texture_status'],
                    'glow_index'         => (int) $scan['glow_index'],
                    'glow_status'        => $scan['glow_status'],
                    'pore_health_score'  => (int) $scan['pore_health_score'],
                    'pore_health_status' => $scan['pore_health_status'],
                    'elasticity_score'   => (int) $scan['elasticity_score'],
                    'elasticity_status'  => $scan['elasticity_status'],
                    'neumera_insight'    => $scan['neumera_insight'] ?? null,
                ]);

                if (!empty($validated['recommendations'])) {
                    foreach ($validated['recommendations'] as $rec) {
                        $skinScan->recommendations()->create([
                            'icon_type'           => $rec['icon_type'],
                            'recommendation_text' => $rec['recommendation_text'],
                        ]);
                    }
                }

                if ($userLimit->skin_scans_limit > 0) {
                    $userLimit->decrement('skin_scans_limit');
                } else {
                    $userLimit->decrement('skin_scans_topup_limit');
                }

                return $skinScan;
            });

            return response()->json([
                'success' => true,
                'message' => 'Skin scan saved successfully. Quota decremented.',
                'data'    => $skinScan->load('recommendations'),
            ], 201);

        } catch (\Throwable $e) { 
            Log::error('DB Transaction Error in Skin Scan: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save scan result.',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile() . ' on line ' . $e->getLine(),
            ], 500);
        }
    }

    public function historyByDate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $user = $request->user();

        $scans = SkinScan::where('user_id', $user->id)
            ->whereDate('created_at', $validated['date'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'overall_score', 'created_at']);

        return response()->json([
            'success' => true,
            'date'    => $validated['date'],
            'count'   => $scans->count(),
            'data'    => $scans,
        ], 200);
    }
}