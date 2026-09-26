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
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user. Authorization token missing or invalid.',
            ], 401);
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
            $uploadedFile = null;
            if ($request->hasFile('src_file_image')) {
                $uploadedFile = $request->file('src_file_image');
            } elseif ($request->hasFile('image')) {
                $uploadedFile = $request->file('image');
            }

            $storedImagePath = null;
            if ($uploadedFile) {
                // Validate the file itself
                $request->validate([
                    'src_file_image' => 'file|image|mimes:jpg,jpeg,png,webp|max:15360', // 15MB max
                ]);

                $storedImagePath = $uploadedFile->store('skin_scans/' . $user->id, 'public');
                // If you want a full public URL instead of a relative path:
                // $storedImagePath = Storage::disk('public')->url($storedImagePath);
            }

            if ($request->has('output')) {
                $validated = $request->validate([
                    'src_file_image'     => 'nullable',
                    'dst_actions'        => 'nullable|array',
                    'output'             => 'required|array|min:1',
                    'output.*.type'      => 'required|string',
                    'output.*.ui_score'  => 'required|numeric',
                    'output.*.raw_score' => 'nullable|numeric',
                    'output.*.mask_urls' => 'nullable|array',
                ]);

                $scores = [];
                foreach ($validated['output'] as $item) {
                    $type = $item['type'];
                    $scores[$type] = (int) round($item['ui_score']);
                }

                if (count($scores) === 0 || array_sum($scores) === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No skin was detected in the scan. Please retake the scan with your face clearly in view.',
                        'code'    => 'NO_SKIN_DETECTED',
                    ], 422);
                }

                $rednessScore    = $scores['hd_redness'] ?? 0;
                $glowIndex       = $scores['hd_radiance'] ?? 0;
                $poreHealthScore = $scores['hd_pore'] ?? 0;
                $textureScore    = $scores['hd_skin_type'] ?? 0;
                $hydrationScore  = $scores['hd_moisture'] ?? 0;
                $elasticityScore = $scores['hd_firmness'] ?? 0;

                $overallScore = (int) round(array_sum($scores) / count($scores));

                if ($storedImagePath) {
                    $imagePath = $storedImagePath;
                } else {
                    $imagePath = $request->input('src_file_image');
                    if ($imagePath === 'null' || empty($imagePath)) {
                        $imagePath = $request->input('image_path');
                    }
                }

                $maskUrls = [];
                foreach ($validated['output'] as $item) {
                    if (!empty($item['mask_urls'])) {
                        $maskUrls[$item['type']] = $item['mask_urls'];
                    }
                }

                $scanData = [
                    'user_id'            => $user->id,
                    'image_path'         => $imagePath,
                    'overall_score'      => $overallScore,
                    'hydration_score'    => $hydrationScore,
                    'hydration_status'   => $this->deriveStatus($hydrationScore),
                    'redness_score'      => $rednessScore,
                    'redness_status'     => $this->deriveStatus($rednessScore),
                    'texture_score'      => $textureScore,
                    'texture_status'     => $this->deriveStatus($textureScore),
                    'glow_index'         => $glowIndex,
                    'glow_status'        => $this->deriveStatus($glowIndex),
                    'pore_health_score'  => $poreHealthScore,
                    'pore_health_status' => $this->deriveStatus($poreHealthScore),
                    'elasticity_score'   => $elasticityScore,
                    'elasticity_status'  => $this->deriveStatus($elasticityScore),
                    'mask_urls'          => !empty($maskUrls) ? $maskUrls : null,
                    'neumera_insight'    => null,
                ];

            } else {
                if (!$request->has('scan') && $request->has('metrics')) {
                    $request->merge(['scan' => $request->input('metrics')]);
                }

                $validated = $request->validate([
                    'image_path'                 => 'nullable|string',
                    'mask_urls'                  => 'nullable|array',
                    'scan'                       => 'required|array',
                    'scan.overall_score'         => 'required|numeric',
                    'scan.hydration_score'       => 'required|numeric',
                    'scan.hydration_status'      => 'nullable|string',
                    'scan.redness_score'         => 'required|numeric',
                    'scan.redness_status'        => 'nullable|string',
                    'scan.texture_score'         => 'required|numeric',
                    'scan.texture_status'        => 'nullable|string',
                    'scan.glow_index'            => 'required|numeric',
                    'scan.glow_status'           => 'nullable|string',
                    'scan.pore_health_score'     => 'required|numeric',
                    'scan.pore_health_status'    => 'nullable|string',
                    'scan.elasticity_score'      => 'required|numeric',
                    'scan.elasticity_status'     => 'nullable|string',
                    'scan.neumera_insight'       => 'nullable|string',
                    'scan.mask_urls'             => 'nullable|array',
                ]);

                $scan = $validated['scan'];

                $scanData = [
                    'user_id'            => $user->id,
                    // ---- Use uploaded file path if present, else fall back to validated string ----
                    'image_path'         => $storedImagePath ?? ($validated['image_path'] ?? null),
                    'overall_score'      => (int) $scan['overall_score'],
                    'hydration_score'    => (int) $scan['hydration_score'],
                    'hydration_status'   => $scan['hydration_status'] ?? $this->deriveStatus((int)$scan['hydration_score']),
                    'redness_score'      => (int) $scan['redness_score'],
                    'redness_status'     => $scan['redness_status'] ?? $this->deriveStatus((int)$scan['redness_score']),
                    'texture_score'      => (int) $scan['texture_score'],
                    'texture_status'     => $scan['texture_status'] ?? $this->deriveStatus((int)$scan['texture_score']),
                    'glow_index'         => (int) $scan['glow_index'],
                    'glow_status'        => $scan['glow_status'] ?? $this->deriveStatus((int)$scan['glow_index']),
                    'pore_health_score'  => (int) $scan['pore_health_score'],
                    'pore_health_status' => $scan['pore_health_status'] ?? $this->deriveStatus((int)$scan['pore_health_score']),
                    'elasticity_score'   => (int) $scan['elasticity_score'],
                    'elasticity_status'  => $scan['elasticity_status'] ?? $this->deriveStatus((int)$scan['elasticity_score']),
                    'mask_urls'          => $scan['mask_urls'] ?? $validated['mask_urls'] ?? null,
                    'neumera_insight'    => $scan['neumera_insight'] ?? null,
                ];
            }

            // Calculate score change, comparison text, and status label vs previous scan
            $previousScan = SkinScan::where('user_id', $user->id)->latest('created_at')->first();
            $currentScore = (int) ($scanData['overall_score'] ?? 0);
            $scoreDiff = $previousScan ? ($currentScore - (int) $previousScan->overall_score) : 0;
            $comparisonText = ($scoreDiff >= 0 ? "+{$scoreDiff}" : "{$scoreDiff}") . 'pts vs last scan';
            $statusLabel = $this->deriveStatusLabel($currentScore);

            $scanData['status_label'] = $statusLabel;
            $scanData['score_change'] = $scoreDiff;
            $scanData['comparison_text'] = $comparisonText;

            // Save skin scan and decrement quota within a database transaction
            $skinScan = DB::transaction(function () use ($scanData, $userLimit) {
                $skinScan = SkinScan::create($scanData);
                $skinScan->update([
                    'findings' => $this->deriveDefaultFindings($skinScan),
                ]);

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

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Failed: Missing or invalid payload parameters.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DB Transaction Error in Skin Scan: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
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

    /**
     * Store or update insights and recommendations received from AI service
     */
    public function updateAiInsights(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'neumera_insight'                       => 'nullable|string',
            'recommendations'                       => 'nullable|array',
            'recommendations.*.icon_type'           => 'required_with:recommendations|string',
            'recommendations.*.recommendation_text' => 'required_with:recommendations|string',
        ]);

        $skinScan = SkinScan::find($id);

        if (!$skinScan) {
            return response()->json([
                'success' => false,
                'message' => 'Skin scan record not found.',
            ], 404);
        }

        DB::transaction(function () use ($skinScan, $validated) {
            if (array_key_exists('neumera_insight', $validated)) {
                $skinScan->update([
                    'neumera_insight' => $validated['neumera_insight'],
                ]);
            }

            if (!empty($validated['recommendations'])) {
                $skinScan->recommendations()->delete();
                foreach ($validated['recommendations'] as $rec) {
                    $skinScan->recommendations()->create([
                        'icon_type'           => $rec['icon_type'],
                        'recommendation_text' => $rec['recommendation_text'],
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'AI insights and recommendations updated successfully.',
            'data'    => $skinScan->load('recommendations'),
        ], 200);
    }

    /**
     * Helper to derive status string from numerical score
     */
    private function deriveStatus(int $score): string
    {
        if ($score >= 80) {
            return 'Good';
        } elseif ($score >= 50) {
            return 'Fair';
        }
        return 'Low';
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

    /**
     * Get Beauty & Radiance Overview directly from skin_scans table
     */
    public function getBeautyOverview(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $days = (int) ($request->input('days') ?? $request->query('days') ?? 30);
        $includeCorrelations = filter_var($request->input('include_correlations', true), FILTER_VALIDATE_BOOLEAN);

        // Optional sync from AI service if requested
        if ($request->boolean('sync_ai') || $request->boolean('refresh')) {
            $this->fetchAndSaveFromAi((int) $userId, $days);
        }

        $latestScan = SkinScan::where('user_id', $userId)
            ->latest('created_at')
            ->first();

        if (!$latestScan) {
            return response()->json([
                'success'      => false,
                'message'      => 'No skin scan data found for this user.',
                'today'        => null,
                'history'      => [],
                'correlations' => null,
                'ai_insights'  => null,
                'tabs'         => ['Today', 'History', 'Correlations'],
            ], 200);
        }

        // Calculate comparison if needed
        $scoreChange = $latestScan->score_change;
        if ($scoreChange === null) {
            $prev = SkinScan::where('user_id', $userId)
                ->where('id', '!=', $latestScan->id)
                ->where('created_at', '<=', $latestScan->created_at)
                ->latest('created_at')
                ->first();
            $scoreChange = $prev ? ((int) $latestScan->overall_score - (int) $prev->overall_score) : 0;
        }

        $comparisonText = $latestScan->comparison_text ?: $this->deriveComparisonText($latestScan, (int) $userId);
        $statusLabel = $latestScan->status_label ?: $this->deriveStatusLabel((int) $latestScan->overall_score);
        $findings = $latestScan->findings ?: $this->deriveDefaultFindings($latestScan);

        $today = [
            'id'                  => $latestScan->id,
            'user_id'             => $latestScan->user_id,
            'image_path'          => $latestScan->image_path,
            'overall_score'       => (int) $latestScan->overall_score,
            'hydration_score'     => (int) $latestScan->hydration_score,
            'redness_score'       => (int) $latestScan->redness_score,
            'texture_score'       => (int) $latestScan->texture_score,
            'glow_index'          => (int) $latestScan->glow_index,
            'pore_health_score'   => (int) $latestScan->pore_health_score,
            'elasticity_score'    => (int) $latestScan->elasticity_score,
            'hydration_status'    => $latestScan->hydration_status,
            'redness_status'      => $latestScan->redness_status,
            'texture_status'      => $latestScan->texture_status,
            'glow_status'         => $latestScan->glow_status,
            'pore_health_status'  => $latestScan->pore_health_status,
            'elasticity_status'   => $latestScan->elasticity_status,
            'neumera_insight'     => $latestScan->neumera_insight,
            'created_at'          => $latestScan->created_at?->format('Y-m-d\TH:i:s'),
            'updated_at'          => $latestScan->updated_at?->format('Y-m-d\TH:i:s'),
            'status_label'        => $statusLabel,
            'findings'            => $findings,
            'score_change'        => (int) $scoreChange,
            'comparison_text'     => $comparisonText,
        ];

        // Fetch history for the requested days
        $scans = SkinScan::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->orderBy('created_at', 'desc')
            ->get();

        if ($scans->isEmpty()) {
            $scans = collect([$latestScan]);
        }

        $history = $scans->map(function ($scan) {
            return [
                'date'         => $scan->created_at->format('Y-m-d'),
                'day_of_week'  => $scan->created_at->format('l'),
                'score'        => (int) $scan->overall_score,
                'days_ago'     => (int) $scan->created_at->diffInDays(now()),
                'status_label' => $scan->status_label ?: $this->deriveStatusLabel((int) $scan->overall_score),
            ];
        })->values()->all();

        // Correlations
        $correlations = null;
        if ($includeCorrelations) {
            $correlations = $latestScan->correlations ?: $this->buildCorrelations((int) $userId, $scans, $latestScan);
        }

        // AI Insights
        $aiInsights = $latestScan->ai_insights ?: $this->buildAiInsights($latestScan);

        return response()->json([
            'success'      => true,
            'today'        => $today,
            'history'      => $history,
            'correlations' => $correlations,
            'ai_insights'  => $aiInsights,
            'tabs'         => ['Today', 'History', 'Correlations'],
        ], 200);
    }

    /**
     * Save AI beauty-overview payload directly into skin_scans table
     */
    public function saveBeautyOverview(Request $request): JsonResponse
    {
        $payload = $request->all();
        $userId = auth('sanctum')->id() ?? $request->input('user_id') ?? ($payload['today']['user_id'] ?? null);

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User ID is required to save beauty overview data.',
            ], 422);
        }

        try {
            $skinScan = DB::transaction(function () use ($payload, $userId) {
                return $this->saveBeautyOverviewData($payload, (int) $userId);
            });

            return response()->json([
                'success' => true,
                'message' => 'Beauty overview data saved successfully in skin_scans table.',
                'data'    => $skinScan->fresh()->load('recommendations'),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error saving beauty overview: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save beauty overview data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync with external AI beauty overview API and persist to skin_scans table
     */
    public function syncBeautyOverview(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $days = (int) ($request->input('days') ?? $request->query('days') ?? 30);
        $aiData = $this->fetchAndSaveFromAi((int) $userId, $days);

        if ($aiData) {
            return response()->json(array_merge(['success' => true], $aiData), 200);
        }

        // Fallback to local DB if AI service is unavailable
        return $this->getBeautyOverview($request);
    }

    /**
     * Combined GET/POST handler for /api/v1/beauty-overview
     */
    public function handleBeautyOverview(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            if ($request->has('today') || $request->has('findings') || $request->has('overall_score')) {
                return $this->saveBeautyOverview($request);
            }
            if ($request->boolean('sync')) {
                return $this->syncBeautyOverview($request);
            }
        }

        return $this->getBeautyOverview($request);
    }

    /**
     * Save/update beauty overview payload into skin_scans table
     */
    public function saveBeautyOverviewData(array $payload, ?int $userId = null): SkinScan
    {
        $todayData = $payload['today'] ?? $payload;
        $targetUserId = $todayData['user_id'] ?? $userId ?? ($payload['user_id'] ?? auth('sanctum')->id());

        if (!$targetUserId) {
            throw new \InvalidArgumentException('User ID is required to save beauty overview data.');
        }

        $scanId = $todayData['id'] ?? null;
        $skinScan = null;

        if ($scanId) {
            $skinScan = SkinScan::where('id', $scanId)->where('user_id', $targetUserId)->first();
        }

        if (!$skinScan) {
            $skinScan = SkinScan::where('user_id', $targetUserId)->latest('created_at')->first();
        }

        $updateFields = [];

        if (isset($todayData['status_label'])) {
            $updateFields['status_label'] = $todayData['status_label'];
        }
        if (isset($todayData['score_change'])) {
            $updateFields['score_change'] = (int) $todayData['score_change'];
        }
        if (isset($todayData['comparison_text'])) {
            $updateFields['comparison_text'] = $todayData['comparison_text'];
        }
        if (isset($todayData['neumera_insight'])) {
            $updateFields['neumera_insight'] = $todayData['neumera_insight'];
        }
        if (isset($todayData['findings'])) {
            $updateFields['findings'] = $todayData['findings'];
        }

        $correlations = $payload['correlations'] ?? ($todayData['correlations'] ?? null);
        if ($correlations !== null) {
            $updateFields['correlations'] = $correlations;
        }

        $aiInsights = $payload['ai_insights'] ?? ($todayData['ai_insights'] ?? null);
        if ($aiInsights !== null) {
            $updateFields['ai_insights'] = $aiInsights;
        }

        if ($skinScan) {
            $skinScan->update($updateFields);
        } else {
            // Only if user had no scan record at all, create an initial record
            $updateFields['user_id'] = $targetUserId;
            $updateFields['overall_score'] = (int) ($todayData['overall_score'] ?? 75);
            $updateFields['hydration_score'] = (int) ($todayData['hydration_score'] ?? 70);
            $updateFields['redness_score'] = (int) ($todayData['redness_score'] ?? 75);
            $updateFields['texture_score'] = (int) ($todayData['texture_score'] ?? 75);
            $updateFields['glow_index'] = (int) ($todayData['glow_index'] ?? 75);
            $updateFields['pore_health_score'] = (int) ($todayData['pore_health_score'] ?? 70);
            $updateFields['elasticity_score'] = (int) ($todayData['elasticity_score'] ?? 75);
            $skinScan = SkinScan::create($updateFields);
        }

        // Sync recommendations to skin_scan_recommendations table
        if (!empty($aiInsights['recommendations']) && is_array($aiInsights['recommendations'])) {
            $skinScan->recommendations()->delete();
            foreach ($aiInsights['recommendations'] as $rec) {
                $recText = is_string($rec) ? $rec : ($rec['recommendation_text'] ?? '');
                $iconType = 'drop';
                $lower = strtolower($recText);
                if (str_contains($lower, 'sleep') || str_contains($lower, 'night')) {
                    $iconType = 'moon';
                } elseif (str_contains($lower, 'sun') || str_contains($lower, 'spf')) {
                    $iconType = 'sun';
                }
                $skinScan->recommendations()->create([
                    'icon_type'           => $iconType,
                    'recommendation_text' => $recText,
                ]);
            }
        }

        return $skinScan;
    }

    /**
     * Call AI service and store overview into database
     */
    private function fetchAndSaveFromAi(int $userId, int $days = 30): ?array
    {
        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/beauty-overview";

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->post($url, [
                    'user_id'              => $userId,
                    'days'                 => $days,
                    'include_correlations' => true,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && (!empty($data['today']) || !empty($data['overall_score']))) {
                    $this->saveBeautyOverviewData($data, $userId);
                    return $data;
                }
            } else {
                Log::warning("AI beauty-overview returned status {$response->status()}", [
                    'user_id' => $userId,
                    'body'    => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI beauty-overview call exception: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
        }

        return null;
    }

    /**
     * Build dynamic correlations fallback from recent scans
     */
    private function buildCorrelations(int $userId, $scans, SkinScan $latestScan): array
    {
        $recent7Scans = SkinScan::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->orderBy('created_at', 'asc')
            ->get();

        $chartData = [];
        foreach ($recent7Scans as $scan) {
            $chartData[] = [
                'day'         => $scan->created_at->format('D'),
                'date'        => $scan->created_at->format('Y-m-d'),
                'skin_score'  => (int) $scan->overall_score,
                'sleep_hours' => null,
            ];
        }

        if (empty($chartData) && $latestScan) {
            $chartData[] = [
                'day'         => $latestScan->created_at->format('D'),
                'date'        => $latestScan->created_at->format('Y-m-d'),
                'skin_score'  => (int) $latestScan->overall_score,
                'sleep_hours' => null,
            ];
        }

        return [
            'sleep_skin' => [
                'correlation_detected'  => true,
                'correlation_strength'  => 75,
                'correlation_direction' => 'positive',
                'insight'               => 'Monitoring sleep and skin correlation. More data will improve accuracy.',
                'chart_data'            => $chartData,
            ],
            'cycle_phases' => [
                'phase_breakdown' => [
                    'menstrual' => [
                        'label'       => 'Menstrual (D1-5)',
                        'score'       => 0,
                        'description' => 'Scan your skin during your menstrual phase to unlock personalized insights on hydration, redness, glow, and texture.',
                    ],
                    'follicular' => [
                        'label'       => 'Follicular (D6-13)',
                        'score'       => 0,
                        'description' => "Start scanning during your follicular phase to unlock personalized insights about your skin's hydration, glow, and texture patterns.",
                    ],
                    'ovulation' => [
                        'label'       => 'Ovulation (D14)',
                        'score'       => 0,
                        'description' => "Log a few scans during your ovulation phase to unlock personalized insights about your skin's real performance.",
                    ],
                    'luteal' => [
                        'label'       => 'Luteal (D15-28)',
                        'score'       => 0,
                        'description' => "Log a few scans during your luteal phase to unlock personalized insights on how your skin actually behaves.",
                    ],
                ],
                'best_phase'  => 'ovulation',
                'worst_phase' => 'menstrual',
            ],
        ];
    }

    /**
     * Build AI insights fallback from scan data
     */
    private function buildAiInsights(SkinScan $scan): array
    {
        $recs = $scan->recommendations->pluck('recommendation_text')->all();
        if (empty($recs)) {
            $recs = [
                'Use a BHA (salicylic acid) exfoliant 2-3x weekly to unclog pores and refine texture',
                'Layer a hyaluronic acid serum on damp skin to boost hydration during your period',
                'Apply a niacinamide serum daily to minimize pore appearance and balance oil',
                'Replenish electrolytes and drink 2.5L+ water daily to support hydration given your high activity',
                'Use a hydrating sheet mask or overnight sleeping mask 2x this week for extra glow',
                'Avoid harsh actives like strong retinoids during menstruation to prevent sensitivity',
            ];
        }

        return [
            'overall_assessment' => $scan->neumera_insight ?: "Your skin is performing well overall at {$scan->overall_score}/100. Elasticity and redness control are your standout strengths, while pore health needs the most attention right now.",
            'phase_impact'       => "Hormonal fluctuations can cause skin hydration and oil levels to fluctuate. Your current hydration ({$scan->hydration_score}) and glow ({$scan->glow_index}) scores reflect this typical trend.",
            'sleep_correlation'  => "Quality sleep is critical for overnight skin repair, boosting collagen production and hydration retention. Prioritizing 7-9 hours tonight will directly improve tomorrow's skin score.",
            'key_focus_areas'    => [
                'pore health',
                'hydration',
                'glow enhancement',
            ],
            'recommendations'    => $recs,
            'routine_suggestion' => 'AM: Gentle cream cleanser → niacinamide serum → hyaluronic acid → moisturizer → SPF 30+. PM: Double cleanse → BHA (alternate nights) → hydrating essence → peptide serum → rich moisturizer or sleeping mask.',
            'confidence_score'   => 88,
        ];
    }

    /**
     * Derive status label (e.g. Radiant, Good, Fair, Needs Care)
     */
    private function deriveStatusLabel(int $score): string
    {
        if ($score >= 75) {
            return 'Radiant';
        } elseif ($score >= 68) {
            return 'Good';
        } elseif ($score >= 50) {
            return 'Fair';
        }
        return 'Needs Care';
    }

    /**
     * Derive comparison text vs previous scan
     */
    private function deriveComparisonText(SkinScan $latestScan, int $userId): string
    {
        $previousScan = SkinScan::where('user_id', $userId)
            ->where('id', '!=', $latestScan->id)
            ->where('created_at', '<=', $latestScan->created_at)
            ->latest('created_at')
            ->first();

        $diff = $previousScan ? ((int) $latestScan->overall_score - (int) $previousScan->overall_score) : 0;
        return ($diff >= 0 ? "+{$diff}" : "{$diff}") . 'pts vs last scan';
    }

    /**
     * Derive default 4 findings for scan analysis
     */
    private function deriveDefaultFindings(SkinScan $scan): array
    {
        $hydrationScore = (int) $scan->hydration_score;
        $poreScore = (int) $scan->pore_health_score;
        $rednessScore = (int) $scan->redness_score;
        $glowScore = (int) $scan->glow_index;

        return [
            [
                'finding' => 'Moisture barrier',
                'status'  => "Hydration levels are moderately adequate at {$hydrationScore}/100, indicating " . ($hydrationScore >= 75 ? 'a well-protected moisture barrier with optimal lipid replenishment.' : 'a functional but slightly compromised moisture barrier that would benefit from enhanced humectant support and lipid replenishment.'),
                'badge'   => $hydrationScore >= 80 ? 'healthy' : ($hydrationScore >= 65 ? 'good' : 'moderate'),
                'score'   => $hydrationScore,
            ],
            [
                'finding' => 'Pore congestion',
                'status'  => "Pore health measures {$poreScore}/100, suggesting " . ($poreScore >= 75 ? 'minimal blockage and clear skin tone across the T-zone.' : 'mild congestion with visible pore prominence in select areas, requiring gentle exfoliation to improve clarity.'),
                'badge'   => $poreScore >= 80 ? 'healthy' : ($poreScore >= 65 ? 'good' : 'moderate'),
                'score'   => $poreScore,
            ],
            [
                'finding' => 'Inflammation markers',
                'status'  => "Redness score of {$rednessScore}/100 reflects " . ($rednessScore >= 80 ? 'low inflammatory activity and minimal vascular reactivity, indicating a calm, well-regulated skin surface.' : 'mild inflammatory activity with slight redness around cheeks.'),
                'badge'   => $rednessScore >= 80 ? 'healthy' : ($rednessScore >= 65 ? 'good' : 'mild'),
                'score'   => $rednessScore,
            ],
            [
                'finding' => 'Melanin uniformity',
                'status'  => "Glow rating of {$glowScore}/100 combined with strong texture indicates fairly even melanin distribution with mild tonal variation, showing generally uniform pigmentation and healthy light-reflective qualities across the complexion.",
                'badge'   => $glowScore >= 80 ? 'healthy' : ($glowScore >= 65 ? 'good' : 'moderate'),
                'score'   => $glowScore,
            ],
        ];
    }
}