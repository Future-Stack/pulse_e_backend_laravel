<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\GsmCheckinLog;
use App\Models\HormoneSnapshot;
use App\Models\LabReport;
use App\Models\MenopauseExportSnapshot;
use App\Models\MenopauseInsightSnapshot;
use App\Models\MenopauseSymptomInsight;
use App\Models\MenopauseSymptomSnapshot;
use App\Models\MenstrualCycle;
use App\Models\PerimenopauseProfile;
use App\Models\SymptomLog;
use App\Models\TerraActivityData;
use App\Models\User;
use App\Models\VasomotorLog;
use App\Services\VasomotorWearableSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PerimenopauseController extends Controller
{
    /**
     * Unified Overview for Perimenopause (Symptoms, Insights, Export tabs).
     * Integrates with external AI service (ai.fightthenumber.com),
     * persists responses in database snapshots, and renders dynamic UI contract.
     *
     * GET|POST /api/v1/perimenopause/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $userId = (int) $userId;
        $tab = strtolower($request->input('tab') ?? $request->query('tab') ?? 'symptoms');
        $defaultPeriod = ($tab === 'insights') ? '90d' : '7d';
        $period = (string) ($request->input('period') ?? $request->query('period') ?? $defaultPeriod);
        $forceRefresh = $request->boolean('refresh') || $request->boolean('sync');

        $profile = $this->getOrCreateProfile($userId);
        app(VasomotorWearableSyncService::class)->syncUserWeeklyVasomotor($userId);

        $todayStr = today()->toDateString();

        $data = [
            'active_tab' => $tab,
            'header'     => [
                'title'      => 'Perimenopause',
                'subtitle'   => 'Perimenopause · Stage tracker ' . ($profile->is_tracker_active ? 'active' : 'inactive'),
                'stage_card' => [
                    'title'    => 'TRANSITION STAGE TRACKER',
                    'stage'    => $profile->stage_title ?: 'Perimenopause',
                    'subtitle' => $profile->stage_subtitle ?: ($profile->is_tracker_active ? 'Stage tracker active' : 'Stage tracker inactive'),
                    'status'   => $profile->is_tracker_active ? 'active' : 'inactive',
                ],
            ],
        ];

        if ($tab === 'insights') {
            // 1. Check or Fetch Insights from AI & Database
            $snapshot = $this->getOrSyncInsightSnapshot($userId, $period, $forceRefresh);
            $data['insights'] = $this->formatInsightsData($userId, $snapshot);
        } elseif ($tab === 'export') {
            // 2. Check or Fetch Clinical Export from AI & Database
            $snapshot = $this->getOrSyncExportSnapshot($userId, $period, $forceRefresh);
            $data['export'] = $this->formatExportData($profile, $userId, $snapshot);
        } else {
            // 3. Default: Symptoms tab - Check or Fetch Symptoms from AI & Database
            $snapshot = $this->getOrSyncSymptomSnapshot($userId, $period, $forceRefresh);

            // Refine stage card if AI snapshot contains specific stage
            if ($snapshot && !empty($snapshot->transition_stage_tracker)) {
                $tracker = $snapshot->transition_stage_tracker;
                if (!empty($tracker['menopause_stage']) && $tracker['menopause_stage'] !== 'unknown') {
                    $data['header']['stage_card']['stage'] = ucwords(str_replace('_', ' ', $tracker['menopause_stage']));
                }
                if (!empty($tracker['months_since_last_period'])) {
                    $data['header']['stage_card']['subtitle'] = "Irregular cycles for {$tracker['months_since_last_period']} months · FSH elevated";
                }
            }

            $data['symptoms'] = $this->formatSymptomsData($profile, $userId, $snapshot);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Dedicated Symptoms tab endpoint.
     * GET|POST /api/v1/perimenopause/symptoms
     */
    public function symptoms(Request $request): JsonResponse
    {
        $request->merge(['tab' => 'symptoms']);
        return $this->overview($request);
    }

    /**
     * Dedicated Insights tab endpoint.
     * GET|POST /api/v1/perimenopause/insights
     */
    public function insights(Request $request): JsonResponse
    {
        $request->merge(['tab' => 'insights']);
        return $this->overview($request);
    }

    /**
     * Dedicated Export tab endpoint.
     * GET|POST /api/v1/perimenopause/export
     */
    public function export(Request $request): JsonResponse
    {
        $request->merge(['tab' => 'export']);
        return $this->overview($request);
    }

    /**
     * Save Intimate & Urinary Health (GSM) Check-in.
     * POST /api/v1/perimenopause/gsm-checkin
     */
    public function saveGsmCheckin(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $userId = (int) $userId;

        $validated = $request->validate([
            'vaginal_dryness'   => 'required|in:none,mild,moderate,severe,very_severe',
            'urinary_frequency' => 'required|in:none,mild,moderate,severe,very_severe',
            'pelvic_discomfort' => 'required|in:none,mild,moderate,severe,very_severe',
            'libido_impact'     => 'required|in:none,mild,moderate,severe,very_severe',
        ]);

        $log = GsmCheckinLog::create([
            'user_id'           => $userId,
            'checkin_date'      => now()->toDateString(),
            'vaginal_dryness'   => $validated['vaginal_dryness'],
            'urinary_frequency' => $validated['urinary_frequency'],
            'pelvic_discomfort' => $validated['pelvic_discomfort'],
            'libido_impact'     => $validated['libido_impact'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'GSM check-in saved successfully.',
            'data'    => [
                'checkin_date'      => $log->checkin_date->toDateString(),
                'vaginal_dryness'   => ucfirst(str_replace('_', ' ', $log->vaginal_dryness)),
                'urinary_frequency' => ucfirst(str_replace('_', ' ', $log->urinary_frequency)),
                'pelvic_discomfort' => ucfirst(str_replace('_', ' ', $log->pelvic_discomfort)),
                'libido_impact'     => ucfirst(str_replace('_', ' ', $log->libido_impact)),
            ],
        ], 200);
    }

    /**
     * Fetch the latest Intimate & Urinary Health (GSM) Check-in.
     * GET /api/v1/perimenopause/gsm-checkin/{userId?}
     */
    public function getGsmCheckin(Request $request, ?string $userId = null): JsonResponse
    {
        $resolvedUserId = $userId
            ?? auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id');

        if (!$resolvedUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $log = GsmCheckinLog::where('user_id', (int) $resolvedUserId)
            ->orderByDesc('checkin_date')
            ->orderByDesc('id')
            ->first();

        if (!$log) {
            return response()->json([
                'success' => true,
                'message' => 'No GSM check-in found.',
                'data'    => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Latest GSM check-in fetched successfully.',
            'data'    => [
                'id'                => $log->id,
                'checkin_date'      => $log->checkin_date->toDateString(),
                'created_at'        => optional($log->created_at)->toDateTimeString(),
                'vaginal_dryness'   => $log->vaginal_dryness,
                'urinary_frequency' => $log->urinary_frequency,
                'pelvic_discomfort' => $log->pelvic_discomfort,
                'libido_impact'     => $log->libido_impact,
            ],
        ], 200);
    }

    /**
     * Log Vasomotor (Hot Flash) Daily Episodes.
     * POST /api/v1/perimenopause/vasomotor-log
     */
    public function logVasomotor(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $userId = (int) $userId;

        $validated = $request->validate([
            'log_date'       => 'nullable|date',
            'mild_count'     => 'nullable|integer|min:0',
            'moderate_count' => 'nullable|integer|min:0',
            'intense_count'  => 'nullable|integer|min:0',
            'avg_intensity'  => 'nullable|numeric|between:0,10',
            'peak_time'      => 'nullable|string',
        ]);

        $date = $validated['log_date'] ?? now()->toDateString();
        $dayOfWeek = Carbon::parse($date)->format('D');

        $mild = (int) ($validated['mild_count'] ?? 0);
        $mod = (int) ($validated['moderate_count'] ?? 0);
        $intense = (int) ($validated['intense_count'] ?? 0);
        $total = $mild + $mod + $intense;

        if (isset($validated['avg_intensity'])) {
            $avgIntensity = (float) $validated['avg_intensity'];
        } elseif ($total > 0) {
            $avgIntensity = round((($mild * 2.5) + ($mod * 4.5) + ($intense * 7.5)) / $total, 1);
        } else {
            $avgIntensity = 0.0;
        }

        $log = VasomotorLog::updateOrCreate(
            ['user_id' => $userId, 'log_date' => $date],
            [
                'day_of_week'    => $dayOfWeek,
                'mild_count'     => $mild,
                'moderate_count' => $mod,
                'intense_count'  => $intense,
                'total_episodes' => $total,
                'avg_intensity'  => $avgIntensity,
                'peak_time'      => $validated['peak_time'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Vasomotor log updated.',
            'data'    => $log,
        ], 200);
    }

    /**
     * Doctor Consultation Export (Report preview & PDF readiness).
     * GET /api/v1/perimenopause/export-report
     */
    public function exportReport(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user_id is missing.',
            ], 401);
        }

        $userId = (int) $userId;
        $period = (string) ($request->input('period') ?? $request->query('period') ?? '7d');

        $profile = $this->getOrCreateProfile($userId);
        $snapshot = $this->getOrSyncExportSnapshot($userId, $period, false);
        $exportData = $this->formatExportData($profile, $userId, $snapshot);

        return response()->json([
            'success' => true,
            'message' => 'Doctor consultation report generated.',
            'data'    => $exportData,
        ], 200);
    }

    // ==========================================
    // AI Integration & Database Persistence
    // ==========================================

    /**
     * Fetch Symptoms from AI API and insert/update in database snapshot.
     */
    private function getOrSyncSymptomSnapshot(int $userId, string $period, bool $forceRefresh = false): ?MenopauseSymptomSnapshot
    {
        $todayStr = today()->toDateString();

        if (!$forceRefresh) {
            $existing = MenopauseSymptomSnapshot::where('user_id', $userId)
                ->where('period', $period)
                ->whereDate('snapshot_date', $todayStr)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/menopause/symptoms";

        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $userId,
                    'period'  => $period,
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                return MenopauseSymptomSnapshot::updateOrCreate(
                    [
                        'user_id'       => $userId,
                        'snapshot_date' => $todayStr,
                        'period'        => $period,
                    ],
                    [
                        'transition_stage_tracker' => $payload['transition_stage_tracker'] ?? [],
                        'vasomotor_tracker'        => $payload['vasomotor_tracker'] ?? [],
                        'gsm_health'               => $payload['gsm_health'] ?? [],
                        'period_selected'          => $payload['period_selected'] ?? $period,
                        'tabs'                     => $payload['tabs'] ?? ['Symptoms'],
                        'journey_active'           => (bool) ($payload['journey_active'] ?? true),
                        'message'                  => $payload['message'] ?? null,
                        'last_updated_ai'          => now()->toIso8601String(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AI Menopause Symptoms fetch exception: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
        }

        return MenopauseSymptomSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->latest('snapshot_date')
            ->first();
    }

    /**
     * Fetch Insights from AI API and insert/update in database snapshot.
     */
    private function getOrSyncInsightSnapshot(int $userId, string $period, bool $forceRefresh = false): ?MenopauseInsightSnapshot
    {
        $todayStr = today()->toDateString();

        if (!$forceRefresh) {
            $existing = MenopauseInsightSnapshot::where('user_id', $userId)
                ->where('period', $period)
                ->whereDate('snapshot_date', $todayStr)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/menopause/insights";

        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $userId,
                    'period'  => $period,
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                $snapshot = MenopauseInsightSnapshot::updateOrCreate(
                    [
                        'user_id'       => $userId,
                        'snapshot_date' => $todayStr,
                        'period'        => $period,
                    ],
                    [
                        'symptom_matrix'  => $payload['symptom_matrix'] ?? [],
                        'period_selected' => $payload['period_selected'] ?? $period,
                        'tabs'            => $payload['tabs'] ?? ['Insights'],
                        'journey_active'  => (bool) ($payload['journey_active'] ?? true),
                        'message'         => $payload['message'] ?? null,
                        'last_updated_ai' => now()->toIso8601String(),
                    ]
                );

                // Sync correlations into MenopauseSymptomInsight table
                $correlations = $payload['symptom_matrix']['symptom_correlations'] ?? [];
                if (!empty($correlations) && is_array($correlations)) {
                    foreach ($correlations as $index => $item) {
                        $title = !empty($item['title'])
                            ? $item['title']
                            : ((!empty($item['from']) && !empty($item['to'])) ? "{$item['from']} → {$item['to']}" : null);

                        if ($title) {
                            $pct = (int) ($item['percentage'] ?? $item['link_percentage'] ?? $item['correlation_percentage'] ?? 0);
                            MenopauseSymptomInsight::updateOrCreate(
                                ['title' => $title],
                                [
                                    'link_percentage' => $pct,
                                    'description'     => $item['description'] ?? '',
                                    'category'        => 'symptom_matrix',
                                    'display_order'   => $index + 1,
                                ]
                            );
                        }
                    }
                }

                return $snapshot;
            }
        } catch (\Throwable $e) {
            Log::warning('AI Menopause Insights fetch exception: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
        }

        return MenopauseInsightSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->latest('snapshot_date')
            ->first();
    }

    /**
     * Fetch Clinical Export from AI API and insert/update in database snapshot.
     */
    private function getOrSyncExportSnapshot(int $userId, string $period, bool $forceRefresh = false): ?MenopauseExportSnapshot
    {
        $todayStr = today()->toDateString();

        if (!$forceRefresh) {
            $existing = MenopauseExportSnapshot::where('user_id', $userId)
                ->where('period', $period)
                ->whereDate('snapshot_date', $todayStr)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $baseUrl = rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/');
        $url = "{$baseUrl}/api/v1/menopause/export";

        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->acceptJson()
                ->get($url, [
                    'user_id' => $userId,
                    'period'  => $period,
                ]);

            if ($response->successful() && is_array($response->json())) {
                $payload = $response->json();

                return MenopauseExportSnapshot::updateOrCreate(
                    [
                        'user_id'       => $userId,
                        'snapshot_date' => $todayStr,
                        'period'        => $period,
                    ],
                    [
                        'clinical_export' => $payload['clinical_export'] ?? [],
                        'tabs'            => $payload['tabs'] ?? ['Export'],
                        'journey_active'  => (bool) ($payload['journey_active'] ?? true),
                        'message'         => $payload['message'] ?? null,
                        'last_updated_ai' => now()->toIso8601String(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AI Menopause Export fetch exception: ' . $e->getMessage(), [
                'user_id' => $userId,
                'period'  => $period,
            ]);
        }

        return MenopauseExportSnapshot::where('user_id', $userId)
            ->where('period', $period)
            ->latest('snapshot_date')
            ->first();
    }

    // ==========================================
    // Database to UI Transformation
    // ==========================================

    private function getOrCreateProfile(int $userId): PerimenopauseProfile
    {
        $dynamicData = $this->calculateDynamicProfileData($userId);

        $profile = PerimenopauseProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return PerimenopauseProfile::create(array_merge([
                'user_id'           => $userId,
                'is_tracker_active' => true,
            ], $dynamicData));
        }

        $updates = [
            'avg_hot_flashes_per_day'          => $dynamicData['avg_hot_flashes_per_day'],
            'sleep_disruption_nights_per_week' => $dynamicData['sleep_disruption_nights_per_week'],
        ];

        if ($profile->stage_title === 'Perimenopause — Year 2') {
            $updates['stage_title'] = $dynamicData['stage_title'];
        }
        if ($profile->stage_subtitle === 'Irregular cycles for 18 months · FSH elevated') {
            $updates['stage_subtitle'] = $dynamicData['stage_subtitle'];
        }
        if ($profile->irregular_cycles_months == 18) {
            $updates['irregular_cycles_months'] = $dynamicData['irregular_cycles_months'];
        }
        if ($profile->fsh_level == 18.4) {
            $updates['fsh_level'] = $dynamicData['fsh_level'];
            $updates['fsh_status'] = $dynamicData['fsh_status'];
        }
        if ($profile->mood_instability === 'Mild-Moderate') {
            $updates['mood_instability'] = $dynamicData['mood_instability'];
        }

        $profile->update($updates);

        return $profile;
    }

    private function calculateDynamicProfileData(int $userId): array
    {
        // 1. Vasomotor average daily episodes
        $vasoLogs = VasomotorLog::where('user_id', $userId)
            ->where('log_date', '>=', now()->subDays(30))
            ->get();
        $avgHotFlashes = $vasoLogs->isNotEmpty() ? round((float) $vasoLogs->avg('total_episodes'), 1) : 0.0;

        // 2. Sleep disruption nights
        $sleepRecords = TerraActivityData::where('user_id', $userId)
            ->where('type', 'sleep')
            ->where(function ($q) {
                $q->where('data_generated_at', '>=', now()->subDays(7))
                  ->orWhere('created_at', '>=', now()->subDays(7));
            })
            ->get();

        $disruptedNights = 0;
        foreach ($sleepRecords as $sleep) {
            $payload = $sleep->payload;
            if (!is_array($payload)) {
                continue;
            }
            $dataList = $payload['data'] ?? [$payload];
            foreach ($dataList as $entry) {
                $awakeCount = $entry['sleep_durations_data']['awake']['num_awake_events'] ?? 0;
                $awakeSec = $entry['sleep_durations_data']['awake']['duration_seconds'] ?? 0;
                if ($awakeCount >= 2 || $awakeSec > 1800) {
                    $disruptedNights++;
                    break;
                }
            }
        }
        $sleepDisruption = (float) min(7, $disruptedNights);

        // 3. FSH reading
        $fshLevel = 0.0;
        $fshStatus = 'not_tested';

        $latestHormone = HormoneSnapshot::whereHas('cycle', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereNotNull('fsh')->latest('snapshot_date')->first();

        if ($latestHormone && $latestHormone->fsh !== null) {
            $fshLevel = (float) $latestHormone->fsh;
        } else {
            $latestLab = LabReport::where('user_id', $userId)->latest()->first();
            if ($latestLab && is_array($latestLab->biomarkers)) {
                foreach ($latestLab->biomarkers as $marker) {
                    if (is_array($marker) && isset($marker['name']) && strtolower($marker['name']) === 'fsh') {
                        $fshLevel = isset($marker['value']) ? (float) $marker['value'] : 0.0;
                        if (!empty($marker['status'])) {
                            $fshStatus = (string) $marker['status'];
                        }
                        break;
                    }
                }
            }
        }

        if ($fshLevel > 0 && $fshStatus === 'not_tested') {
            if ($fshLevel >= 25.0) {
                $fshStatus = 'postmenopausal';
            } elseif ($fshLevel >= 10.0) {
                $fshStatus = 'elevated';
            } else {
                $fshStatus = 'normal';
            }
        }

        // 4. Irregular cycles
        $cycles = MenstrualCycle::where('user_id', $userId)
            ->whereNotNull('cycle_length')
            ->orderBy('period_start_date', 'desc')
            ->limit(12)
            ->get();

        $irregularMonths = 0;
        if ($cycles->count() >= 2) {
            $lengths = $cycles->pluck('cycle_length')->all();
            $diff = max($lengths) - min($lengths);
            if ($diff >= 7) {
                $oldest = $cycles->last()->period_start_date;
                $irregularMonths = max(1, (int) Carbon::parse($oldest)->diffInMonths(now()));
            }
        }

        // 5. Mood instability
        $recentSymptoms = SymptomLog::whereHas('cycle', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('log_date', '>=', now()->subDays(30))
          ->whereNotNull('mood')
          ->get();

        $moodInstability = 'Not reported';
        if ($recentSymptoms->isNotEmpty()) {
            $distinctMoods = $recentSymptoms->pluck('mood')->unique()->count();
            if ($distinctMoods >= 3) {
                $moodInstability = 'Moderate-High';
            } elseif ($distinctMoods >= 2) {
                $moodInstability = 'Mild-Moderate';
            } else {
                $moodInstability = 'Stable';
            }
        }

        // 6. Stage & Subtitle
        if ($irregularMonths >= 12 || ($fshStatus === 'elevated' && $irregularMonths >= 6)) {
            $stageTitle = 'Perimenopause — Late Transition';
        } elseif ($irregularMonths > 0 || $avgHotFlashes > 0) {
            $stageTitle = 'Perimenopause — Early Transition';
        } else {
            $stageTitle = 'Perimenopause';
        }

        $subtitleParts = [];
        if ($irregularMonths > 0) {
            $subtitleParts[] = "Irregular cycles for {$irregularMonths} months";
        }
        if ($fshLevel > 0) {
            $subtitleParts[] = "FSH " . ($fshStatus !== 'not_tested' ? $fshStatus : "{$fshLevel} mIU/mL");
        }
        if (empty($subtitleParts)) {
            $subtitleParts[] = 'Stage tracker active';
        }
        $stageSubtitle = implode(' · ', $subtitleParts);

        return [
            'stage_title'                      => $stageTitle,
            'stage_subtitle'                   => $stageSubtitle,
            'irregular_cycles_months'          => $irregularMonths,
            'fsh_level'                        => $fshLevel,
            'fsh_status'                       => $fshStatus,
            'avg_hot_flashes_per_day'          => $avgHotFlashes,
            'sleep_disruption_nights_per_week' => $sleepDisruption,
            'mood_instability'                 => $moodInstability,
        ];
    }

    private function formatSymptomsData(PerimenopauseProfile $profile, int $userId, ?MenopauseSymptomSnapshot $snapshot = null): array
    {
        // 1. Vasomotor Chart Data for Current Week (Mon - Sun)
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $logs = VasomotorLog::where('user_id', $userId)
            ->whereBetween('log_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->get()
            ->keyBy(fn($l) => Carbon::parse($l->log_date)->toDateString());

        $chart = collect(range(0, 6))->map(function ($dayIndex) use ($startOfWeek, $logs) {
            $date = $startOfWeek->copy()->addDays($dayIndex);
            $dateStr = $date->toDateString();
            $log = $logs->get($dateStr);

            return [
                'day'      => $date->format('D'),
                'date'     => $dateStr,
                'mild'     => $log ? (int) $log->mild_count : 0,
                'moderate' => $log ? (int) $log->moderate_count : 0,
                'intense'  => $log ? (int) $log->intense_count : 0,
                'total'    => $log ? (int) $log->total_episodes : 0,
            ];
        });

        $totalEpisodes = (int) $chart->sum('total');
        $validLogs = $logs->filter(fn($l) => (float) $l->avg_intensity > 0);
        $avgIntensityNum = $validLogs->isNotEmpty() ? round((float) $validLogs->avg('avg_intensity'), 1) : 0.0;

        // If local logs are zero but snapshot has AI vasomotor data, incorporate it
        $aiVasomotor = $snapshot ? ($snapshot->vasomotor_tracker ?? []) : [];
        if ($totalEpisodes === 0 && !empty($aiVasomotor['total_events'])) {
            $totalEpisodes = (int) $aiVasomotor['total_events'];
            $avgIntensityNum = (float) ($aiVasomotor['avg_severity'] ?? $avgIntensityNum);
        }

        $avgIntensityFormatted = "{$avgIntensityNum}/10";

        // Find peak episode day & time
        $peakLog = $logs->sortByDesc('total_episodes')->first();
        if ($peakLog && $peakLog->total_episodes > 0) {
            $peakTime = $peakLog->peak_time ?: ($peakLog->day_of_week . ' peak');
        } elseif (!empty($aiVasomotor['peak_times']) && is_array($aiVasomotor['peak_times'])) {
            $peakTime = (string) $aiVasomotor['peak_times'][0];
        } else {
            $peakTime = 'None recorded';
        }

        $summaryText = $totalEpisodes > 0
            ? "This week: {$totalEpisodes} episodes · avg intensity {$avgIntensityFormatted} · Peak: {$peakTime}"
            : "This week: 0 episodes recorded";

        // 2. Latest GSM Check-in from Local Log or AI Snapshot
        $latestGsm = GsmCheckinLog::where('user_id', $userId)
            ->latest('checkin_date')
            ->first();

        $aiGsm = $snapshot ? ($snapshot->gsm_health ?? []) : [];

        $dryness = $latestGsm
            ? ucfirst(str_replace('_', ' ', $latestGsm->vaginal_dryness))
            : (!empty($aiGsm['vaginal_dryness']['level']) && $aiGsm['vaginal_dryness']['level'] !== 'not_reported'
                ? ucfirst(str_replace('_', ' ', $aiGsm['vaginal_dryness']['level']))
                : 'None');

        $frequency = $latestGsm
            ? ucfirst(str_replace('_', ' ', $latestGsm->urinary_frequency))
            : (!empty($aiGsm['urinary_frequency']['level']) && $aiGsm['urinary_frequency']['level'] !== 'not_reported'
                ? ucfirst(str_replace('_', ' ', $aiGsm['urinary_frequency']['level']))
                : 'None');

        $pelvic = $latestGsm
            ? ucfirst(str_replace('_', ' ', $latestGsm->pelvic_discomfort))
            : (!empty($aiGsm['pelvic_discomfort']['level']) && $aiGsm['pelvic_discomfort']['level'] !== 'not_reported'
                ? ucfirst(str_replace('_', ' ', $aiGsm['pelvic_discomfort']['level']))
                : 'None');

        $libido = $latestGsm
            ? ucfirst(str_replace('_', ' ', $latestGsm->libido_impact))
            : (!empty($aiGsm['libido_impact']['level']) && $aiGsm['libido_impact']['level'] !== 'not_reported'
                ? ucfirst(str_replace('_', ' ', $aiGsm['libido_impact']['level']))
                : 'None');

        // 3. Dynamic Phase Badge
        $latestCycle = MenstrualCycle::where('user_id', $userId)->latest('period_start_date')->first();
        $phaseBadge = ($latestCycle && !empty($latestCycle->current_phase))
            ? ucwords(str_replace('_', ' ', $latestCycle->current_phase))
            : ($profile->stage_title ?: 'Transition Phase');

        return [
            'vasomotor_tracker' => [
                'title'         => 'VASOMOTOR TRACKER',
                'phase_badge'   => $phaseBadge,
                'chart'         => $chart,
                'legend'        => [
                    'mild'     => 'Mild (1-3)',
                    'moderate' => 'Moderate (4-5)',
                    'intense'  => 'Intense (6+)',
                ],
                'summary'       => [
                    'total_episodes'  => $totalEpisodes,
                    'avg_intensity'   => $avgIntensityFormatted,
                    'peak'            => $peakTime,
                    'summary_text'    => $summaryText,
                ],
            ],
            'gsm' => [
                'title'        => 'INTIMATE & URINARY HEALTH (GSM)',
                'symptoms'     => [
                    ['name' => 'Vaginal dryness', 'value' => $dryness, 'key' => 'vaginal_dryness'],
                    ['name' => 'Urinary frequency', 'value' => $frequency, 'key' => 'urinary_frequency'],
                    ['name' => 'Pelvic discomfort', 'value' => $pelvic, 'key' => 'pelvic_discomfort'],
                    ['name' => 'Libido impact', 'value' => $libido, 'key' => 'libido_impact'],
                ],
                'button_text'  => ($latestGsm || !empty($aiGsm['vaginal_dryness']['level'])) ? 'Update check-in' : 'Log your first check-in',
                'modal_config' => [
                    'title'         => 'Your answers power these symptoms',
                    'subtitle'      => 'GSM DATA SOURCE',
                    'options'       => ['None', 'Mild', 'Moderate', 'Severe', 'Very severe'],
                    'privacy_note'  => 'Private in this prototype. Saved only on this account.',
                    'endpoint'      => '/api/v1/perimenopause/gsm-checkin',
                ],
            ],
        ];
    }

    private function formatInsightsData(int $userId, ?MenopauseInsightSnapshot $snapshot = null): array
    {
        // 1. Check if AI snapshot has symptom correlations
        $snapshotCorrelations = $snapshot ? ($snapshot->symptom_matrix['symptom_correlations'] ?? []) : [];

        if (!empty($snapshotCorrelations) && is_array($snapshotCorrelations)) {
            $items = collect($snapshotCorrelations)->map(function ($corr, $idx) {
                $pct = (int) ($corr['percentage'] ?? $corr['link_percentage'] ?? $corr['correlation_percentage'] ?? 0);
                $title = !empty($corr['title'])
                    ? $corr['title']
                    : ((!empty($corr['from']) && !empty($corr['to'])) ? "{$corr['from']} → {$corr['to']}" : 'Symptom Correlation');

                return [
                    'id'              => $idx + 1,
                    'title'           => $title,
                    'link_percentage' => "{$pct}% link",
                    'description'     => $corr['description'] ?? '',
                    'progress'        => $pct,
                ];
            });

            return [
                'title' => 'SYMPTOM MATRIX INSIGHTS',
                'items' => $items,
            ];
        }

        // 2. Fallback to MenopauseSymptomInsight table from database
        $insights = MenopauseSymptomInsight::orderBy('display_order', 'asc')
            ->get()
            ->map(fn($i) => [
                'id'              => $i->id,
                'title'           => $i->title,
                'link_percentage' => "{$i->link_percentage}% link",
                'description'     => $i->description,
                'progress'        => $i->link_percentage,
            ]);

        return [
            'title' => 'SYMPTOM MATRIX INSIGHTS',
            'items' => $insights,
        ];
    }

    private function formatExportData(PerimenopauseProfile $profile, int $userId, ?MenopauseExportSnapshot $snapshot = null): array
    {
        $latestGsm = GsmCheckinLog::where('user_id', $userId)->latest('checkin_date')->first();

        $gsmSummary = $latestGsm
            ? ucfirst(str_replace('_', ' ', $latestGsm->vaginal_dryness)) . ' dryness & ' . str_replace('_', ' ', $latestGsm->urinary_frequency) . ' frequency'
            : 'No GSM symptoms recorded';

        $avgHotFlashes = ($profile->avg_hot_flashes_per_day > 0)
            ? "{$profile->avg_hot_flashes_per_day}/day (this month)"
            : '0/day';

        $sleepDisruption = ($profile->sleep_disruption_nights_per_week > 0)
            ? "{$profile->sleep_disruption_nights_per_week} nights/week"
            : '0 nights/week';

        $fshReading = ($profile->fsh_level > 0)
            ? "{$profile->fsh_level} mIU/mL" . ($profile->fsh_status && $profile->fsh_status !== 'not_tested' ? " ({$profile->fsh_status})" : '')
            : 'No lab test recorded';

        // Incorporate AI Clinical Export snapshot if present in database
        $aiExport = $snapshot ? ($snapshot->clinical_export ?? []) : [];

        $perimenopauseStage = !empty($aiExport['perimenopause_stage'])
            ? $aiExport['perimenopause_stage']
            : ($profile->stage_title ?: 'Perimenopause');

        if (!empty($aiExport['avg_hot_flashes'])) {
            $avgHotFlashes = $aiExport['avg_hot_flashes'];
        }
        if (!empty($aiExport['sleep_disruption'])) {
            $sleepDisruption = $aiExport['sleep_disruption'];
        }
        if (!empty($aiExport['mood_instability'])) {
            $moodInstability = $aiExport['mood_instability'];
        } else {
            $moodInstability = $profile->mood_instability ?: 'Not reported';
        }
        if (!empty($aiExport['gsm_symptoms']) && $gsmSummary === 'No GSM symptoms recorded') {
            $gsmSummary = $aiExport['gsm_symptoms'];
        }
        if (!empty($aiExport['last_fsh_reading']) && $fshReading === 'No lab test recorded') {
            $fshReading = $aiExport['last_fsh_reading'];
        }

        return [
            'title'          => 'Clinical Consultation Export',
            'subtitle'       => 'Generate a doctor-ready symptom summary for your next appointment.',
            'report_preview' => [
                'perimenopause_stage' => $perimenopauseStage,
                'avg_hot_flashes'     => $avgHotFlashes,
                'sleep_disruption'    => $sleepDisruption,
                'mood_instability'    => $moodInstability,
                'gsm_symptoms'        => $gsmSummary,
                'last_fsh_reading'    => $fshReading,
            ],
            'button_text'    => 'Export PDF',
            'download_ready' => true,
        ];
    }
}
