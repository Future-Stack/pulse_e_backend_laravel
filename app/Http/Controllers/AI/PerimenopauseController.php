<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\GsmCheckinLog;
use App\Models\MenopauseSymptomInsight;
use App\Models\PerimenopauseProfile;
use App\Models\User;
use App\Models\VasomotorLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerimenopauseController extends Controller
{
    /**
     * Unified Overview for Perimenopause (Symptoms, Insights, Export tabs).
     * GET /api/v1/perimenopause/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? $request->query('user_id')
            ?? 1;

        $tab = strtolower($request->input('tab') ?? $request->query('tab') ?? 'symptoms');

        $profile = $this->getOrCreateProfile((int) $userId);
        $this->ensureVasomotorWeeklyLogs((int) $userId);

        $data = [
            'active_tab' => $tab,
            'header' => [
                'title'       => 'Perimenopause',
                'subtitle'    => 'Perimenopause · Stage tracker active',
                'stage_card'  => [
                    'title'    => 'TRANSITION STAGE TRACKER',
                    'stage'    => $profile->stage_title,
                    'subtitle' => $profile->stage_subtitle,
                    'status'   => $profile->is_tracker_active ? 'active' : 'inactive',
                ],
            ],
        ];

        if ($tab === 'insights') {
            $data['insights'] = $this->formatInsightsData();
        } elseif ($tab === 'export') {
            $data['export'] = $this->formatExportData($profile, (int) $userId);
        } else {
            // Default: symptoms
            $data['symptoms'] = $this->formatSymptomsData($profile, (int) $userId);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Save Intimate & Urinary Health (GSM) Check-in.
     * POST /api/v1/perimenopause/gsm-checkin
     */
    public function saveGsmCheckin(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id()
            ?? $request->input('user_id')
            ?? 1;

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
                'checkin_date'       => $log->checkin_date->toDateString(),
                'vaginal_dryness'   => ucfirst(str_replace('_', ' ', $log->vaginal_dryness)),
                'urinary_frequency' => ucfirst(str_replace('_', ' ', $log->urinary_frequency)),
                'pelvic_discomfort' => ucfirst(str_replace('_', ' ', $log->pelvic_discomfort)),
                'libido_impact'     => ucfirst(str_replace('_', ' ', $log->libido_impact)),
            ],
        ], 200);
    }

    /**
     * Fetch the latest Intimate & Urinary Health (GSM) Check-in.
     * GET /api/v1/perimenopause/gsm-checkin
     */
    public function getGsmCheckin(Request $request, string $userId){


        $log = GsmCheckinLog::where('user_id', $userId)
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
            ?? 1;

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

        $mild = $validated['mild_count'] ?? 0;
        $mod = $validated['moderate_count'] ?? 0;
        $intense = $validated['intense_count'] ?? 0;
        $total = $mild + $mod + $intense;

        $log = VasomotorLog::updateOrCreate(
            ['user_id' => $userId, 'log_date' => $date],
            [
                'day_of_week'    => $dayOfWeek,
                'mild_count'     => $mild,
                'moderate_count' => $mod,
                'intense_count'  => $intense,
                'total_episodes' => $total,
                'avg_intensity'  => $validated['avg_intensity'] ?? 4.3,
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
            ?? 1;

        $profile = $this->getOrCreateProfile((int) $userId);
        $exportData = $this->formatExportData($profile, (int) $userId);

        return response()->json([
            'success' => true,
            'message' => 'Doctor consultation report generated.',
            'data'    => $exportData,
        ], 200);
    }

    // ==========================================
    // Internal Helper Methods
    // ==========================================

    private function getOrCreateProfile(int $userId): PerimenopauseProfile
    {
        return PerimenopauseProfile::firstOrCreate(
            ['user_id' => $userId],
            [
                'stage_title'                      => 'Perimenopause — Year 2',
                'stage_subtitle'                   => 'Irregular cycles for 18 months · FSH elevated',
                'is_tracker_active'                => true,
                'irregular_cycles_months'          => 18,
                'fsh_level'                        => 18.4,
                'fsh_status'                       => 'elevated',
                'avg_hot_flashes_per_day'          => 4.3,
                'sleep_disruption_nights_per_week' => 3.2,
                'mood_instability'                 => 'Mild-Moderate',
            ]
        );
    }

    private function ensureVasomotorWeeklyLogs(int $userId): void
    {
        $count = VasomotorLog::where('user_id', $userId)->count();
        if ($count > 0) {
            return;
        }

        // Seed current week Monday - Sunday matching chart design
        $days = [
            ['day' => 'Mon', 'mild' => 2, 'mod' => 3, 'intense' => 1, 'total' => 6],
            ['day' => 'Tue', 'mild' => 1, 'mod' => 2, 'intense' => 0, 'total' => 3],
            ['day' => 'Wed', 'mild' => 3, 'mod' => 4, 'intense' => 1, 'total' => 8],
            ['day' => 'Thu', 'mild' => 2, 'mod' => 5, 'intense' => 4, 'total' => 11], // Peak
            ['day' => 'Fri', 'mild' => 1, 'mod' => 2, 'intense' => 1, 'total' => 4],
            ['day' => 'Sat', 'mild' => 2, 'mod' => 3, 'intense' => 2, 'total' => 7],
            ['day' => 'Sun', 'mild' => 1, 'mod' => 1, 'intense' => 0, 'total' => 2],
        ];

        $startOfWeek = Carbon::now()->startOfWeek();

        foreach ($days as $index => $item) {
            $logDate = $startOfWeek->copy()->addDays($index)->toDateString();
            VasomotorLog::create([
                'user_id'        => $userId,
                'log_date'       => $logDate,
                'day_of_week'    => $item['day'],
                'mild_count'     => $item['mild'],
                'moderate_count' => $item['mod'],
                'intense_count'  => $item['intense'],
                'total_episodes' => $item['total'],
                'avg_intensity'  => 4.3,
                'peak_time'      => $item['day'] === 'Thu' ? 'Thursday evening' : null,
            ]);
        }
    }

    private function formatSymptomsData(PerimenopauseProfile $profile, int $userId): array
    {
        // 1. Vasomotor Chart Data
        $logs = VasomotorLog::where('user_id', $userId)
            ->orderBy('log_date', 'asc')
            ->limit(7)
            ->get();

        $chart = $logs->map(fn($l) => [
            'day'      => $l->day_of_week,
            'date'     => $l->log_date->toDateString(),
            'mild'     => $l->mild_count,
            'moderate' => $l->moderate_count,
            'intense'  => $l->intense_count,
            'total'    => $l->total_episodes,
        ]);

        $totalEpisodes = $logs->sum('total_episodes') ?: 30;

        // 2. Latest GSM Check-in
        $latestGsm = GsmCheckinLog::where('user_id', $userId)
            ->latest('checkin_date')
            ->first();

        $dryness = $latestGsm ? ucfirst(str_replace('_', ' ', $latestGsm->vaginal_dryness)) : 'Moderate';
        $frequency = $latestGsm ? ucfirst(str_replace('_', ' ', $latestGsm->urinary_frequency)) : 'Mild';
        $pelvic = $latestGsm ? ucfirst(str_replace('_', ' ', $latestGsm->pelvic_discomfort)) : 'Mild';
        $libido = $latestGsm ? ucfirst(str_replace('_', ' ', $latestGsm->libido_impact)) : 'Moderate';

        return [
            'vasomotor_tracker' => [
                'title'         => 'VASOMOTOR TRACKER',
                'phase_badge'   => 'Fertile Phase',
                'chart'         => $chart,
                'legend'        => [
                    'mild'     => 'Mild (1-3)',
                    'moderate' => 'Moderate (4-5)',
                    'intense'  => 'Intense (6+)',
                ],
                'summary'       => [
                    'total_episodes'  => $totalEpisodes,
                    'avg_intensity'   => '4.3/10',
                    'peak'            => 'Thursday evening',
                    'summary_text'    => "This week: {$totalEpisodes} episodes · avg intensity 4.3/10 · Peak: Thursday evening",
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
                'button_text'  => $latestGsm ? 'Update check-in' : 'Log your first check-in',
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

    private function formatInsightsData(): array
    {
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

    private function formatExportData(PerimenopauseProfile $profile, int $userId): array
    {
        $latestGsm = GsmCheckinLog::where('user_id', $userId)->latest('checkin_date')->first();
        $dryness = $latestGsm ? strtolower($latestGsm->vaginal_dryness) : 'mild';
        $frequency = $latestGsm ? strtolower($latestGsm->urinary_frequency) : 'frequency';

        return [
            'title'          => 'Clinical Consultation Export',
            'subtitle'       => 'Generate a doctor-ready symptom summary for your next appointment.',
            'report_preview' => [
                'perimenopause_stage' => 'Year 2 (confirmed)',
                'avg_hot_flashes'     => "{$profile->avg_hot_flashes_per_day}/day (this month)",
                'sleep_disruption'    => "{$profile->sleep_disruption_nights_per_week} nights/week",
                'mood_instability'    => $profile->mood_instability,
                'gsm_symptoms'        => ucfirst($dryness) . ' dryness & ' . $frequency,
                'last_fsh_reading'    => "{$profile->fsh_level} mIU/mL ({$profile->fsh_status})",
            ],
            'button_text'    => 'Export PDF',
            'download_ready' => true,
        ];
    }
}
