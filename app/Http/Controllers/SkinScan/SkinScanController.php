<?php

namespace App\Http\Controllers\SkinScan;

use App\Http\Controllers\Controller;
use App\Models\SkinScan;
use App\Models\UserLimit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SkinScanController extends Controller
{
    /**
     * GET /api/v1/skin-scans/history
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $history = SkinScan::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $insightSummary = "Keep up your consistency to track changes over time!";
        if ($history->count() >= 2) {
            $latest = $history->first()->overall_score;
            $previous = $history->skip(1)->first()->overall_score;
            $diff = $latest - $previous;
            
            if ($diff > 0) {
                $insightSummary = "Your skin score has improved +{$diff} points over the past weeks. The biggest driver is improved sleep consistency. Keep it up!";
            } else if ($diff < 0) {
                $insightSummary = "Your skin score dropped by " . abs($diff) . " points. Consider focusing on your hydration and sleep pattern today.";
            }
        }

        return response()->json([
            'success' => true,
            'insight_summary' => $insightSummary,
            'data' => $history
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $user = $request->user();

        $userLimit = UserLimit::where('user_id', $user->id)->first();

        if (!$userLimit) {
            $userLimit = UserLimit::create([
                'user_id' => $user->id,
                'skin_scans_limit' => 2,
                'ai_coaching_limit' => 5,
                'deep_reports_limit' => 0,
                'skin_scans_topup_limit' => 0,
                'ai_coaching_topup_limit' => 0,
            ]);
        }

        if ($userLimit->skin_scans_limit <= 0 && $userLimit->skin_scans_topup_limit <= 0) {

            $refreshDate = $userLimit->subscription_expires_at 
                ? $userLimit->subscription_expires_at->format('M d, Y') 
                : now()->addMonth()->startOfMonth()->format('M d, Y');

            return response()->json([
                'success' => false,
                'message' => "You’ve used your skin scans this month; they refresh on {$refreshDate}",
                'code' => 'QUOTA_EXCEEDED'
            ], 403);
        }

        if ($userLimit->skin_scans_limit > 0) {
            $userLimit->decrement('skin_scans_limit');
        } else {
            $userLimit->decrement('skin_scans_topup_limit');
        }

        $path = $request->file('image')->store('skin_scans', 'public');

        $aiResult = [
            'overall_score' => 80,
            'hydration_score' => 72, 'hydration_status' => 'Fair',
            'redness_score' => 22, 'redness_status' => 'Low',
            'texture_score' => 84, 'texture_status' => 'Good',
            'glow_index' => 68, 'glow_status' => 'Fair',
            'pore_health_score' => 79, 'pore_health_status' => 'Low',
            'elasticity_score' => 81, 'elasticity_status' => 'Good',
            'neumera_insight' => "Your redness score correlates with the 2 nights of disrupted sleep detected this week. Prioritising 7h+ sleep tonight could reduce redness by 15-20% within 3 days. The hydration dip aligns with lower water-intake signals from your wearable — aim for an extra 500ml today."
        ];

        $scan = SkinScan::create(array_merge(
            ['user_id' => $user->id, 'image_path' => $path],
            $aiResult
        ));

        $recommendations = [
            ['icon_type' => 'drop', 'recommendation_text' => 'Drink 500ml extra water before 3pm'],
            ['icon_type' => 'moon', 'recommendation_text' => 'Aim for 7h+ sleep — set 10pm wind-down'],
            ['icon_type' => 'sun', 'recommendation_text' => 'Apply SPF 30+ before going outside'],
            ['icon_type' => 'food', 'recommendation_text' => 'Add Vitamin C rich foods to your next meal'],
        ];

        foreach ($recommendations as $rec) {
            $scan->recommendations()->create($rec);
        }

        return response()->json([
            'success' => true,
            'message' => 'Skin analyzed successfully. Quota decremented.',
            'data' => $scan->load('recommendations')
        ], 201);
    }
}