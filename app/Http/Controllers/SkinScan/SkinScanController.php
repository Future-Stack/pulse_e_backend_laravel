<?php

namespace App\Http\Controllers\SkinScan;

use App\Http\Controllers\Controller;
use App\Models\SkinScan;
use App\Models\UserLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            } else if ($diff < 0) {
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
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $user = $request->user();

        $userLimit = UserLimit::firstOrCreate(
            ['user_id' => $user->id],
            [
                'skin_scans_limit'       => 2,
                'ai_coaching_limit'      => 5,
                'deep_reports_limit'     => 0,
                'skin_scans_topup_limit' => 0,
                'ai_coaching_topup_limit'=> 0,
            ]
        );

        if ($userLimit->skin_scans_limit <= 0 && $userLimit->skin_scans_topup_limit <= 0) {
            $refreshDate = $userLimit->subscription_expires_at
                ? $userLimit->subscription_expires_at->format('M d, Y')
                : now()->addMonth()->startOfMonth()->format('M d, Y');

            return response()->json([
                'success' => false,
                'message' => "You’ve used your skin scans this month; they refresh on {$refreshDate}",
                'code'    => 'QUOTA_EXCEEDED',
            ], 403);
        }

        $uploadedFile = $request->file('image');
        $path = $uploadedFile->store('skin_scans', 'public');

        try {
            $aiResult = $this->getAiAnalysisResult($uploadedFile);
        } catch (\Exception $e) {
            Storage::disk('public')->delete($path);
            Log::error('AI Scan Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to analyze skin at this moment. Please try again later.',
            ], 502);
        }

        try {
            $scan = DB::transaction(function () use ($user, $path, $aiResult, $userLimit) {
                $skinScan = SkinScan::create([
                    'user_id'            => $user->id,
                    'image_path'         => $path,
                    'overall_score'      => $aiResult['overall_score'],
                    'hydration_score'    => $aiResult['hydration_score'],
                    'hydration_status'   => $aiResult['hydration_status'],
                    'redness_score'      => $aiResult['redness_score'],
                    'redness_status'     => $aiResult['redness_status'],
                    'texture_score'      => $aiResult['texture_score'],
                    'texture_status'     => $aiResult['texture_status'],
                    'glow_index'         => $aiResult['glow_index'],
                    'glow_status'        => $aiResult['glow_status'],
                    'pore_health_score'  => $aiResult['pore_health_score'],
                    'pore_health_status' => $aiResult['pore_health_status'],
                    'elasticity_score'   => $aiResult['elasticity_score'],
                    'elasticity_status'  => $aiResult['elasticity_status'],
                    'neumera_insight'    => $aiResult['neumera_insight'],
                ]);

                if (!empty($aiResult['recommendations'])) {
                    foreach ($aiResult['recommendations'] as $rec) {
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
                'message' => 'Skin analyzed successfully. Quota decremented.',
                'data'    => $scan->load('recommendations'),
            ], 201);

        } catch (\Exception $e) {
            Storage::disk('public')->delete($path);
            Log::error('DB Transaction Error in Skin Scan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process scan result.',
            ], 500);
        }
    }

    
    private function getAiAnalysisResult($file): array
    {
        if (config('services.ai_scan.use_mock')) {
            return [
                'overall_score'      => 80,
                'hydration_score'    => 72, 'hydration_status'   => 'Fair',
                'redness_score'      => 22, 'redness_status'     => 'Low',
                'texture_score'      => 84, 'texture_status'     => 'Good',
                'glow_index'         => 68, 'glow_status'        => 'Fair',
                'pore_health_score'  => 79, 'pore_health_status' => 'Low',
                'elasticity_score'   => 81, 'elasticity_status'  => 'Good',
                'neumera_insight'    => "Your redness score correlates with the 2 nights of disrupted sleep detected this week. Prioritising 7h+ sleep tonight could reduce redness by 15-20% within 3 days.",
                'recommendations'    => [
                    ['icon_type' => 'drop', 'recommendation_text' => 'Drink 500ml extra water before 3pm'],
                    ['icon_type' => 'moon', 'recommendation_text' => 'Aim for 7h+ sleep — set 10pm wind-down'],
                    ['icon_type' => 'sun',  'recommendation_text' => 'Apply SPF 30+ before going outside'],
                    ['icon_type' => 'food', 'recommendation_text' => 'Add Vitamin C rich foods to your next meal'],
                ]
            ];
        }

        $response = Http::timeout(15)
            ->withToken(config('services.ai_scan.token'))
            ->attach('image', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post(config('services.ai_scan.url') . '/v1/analyze-skin');

        if ($response->failed()) {
            throw new \Exception('AI Service HTTP Error: ' . $response->body());
        }

        return $response->json('data');
    }
}