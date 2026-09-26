<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CervicalMucusLog;
use App\Models\HormoneSnapshot;
use App\Models\MenstrualCycle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CervicalMucusLogController extends Controller
{
    /**
     * Log daily Cervical Mucus observation & return hormone trends
     *
     * POST /api/v1/cycle-engine/cervical-mucus/log
     * POST /api/v1/cervical-mucus/log
     */
    public function log(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        // Normalize consistency input (e.g., 'egg white' or 'Egg White' to 'egg_white')
        if ($request->has('consistency')) {
            $rawConsistency = strtolower(trim((string) $request->input('consistency')));
            $normalized = match ($rawConsistency) {
                'egg white', 'eggwhite', 'egg-white' => 'egg_white',
                default => $rawConsistency,
            };
            $request->merge(['consistency' => $normalized]);
        }

        $validated = $request->validate([
            'consistency' => 'required|string|in:dry,sticky,creamy,watery,egg_white',
            'log_date'    => 'nullable|date',
            'amount'      => 'nullable|string|in:low,medium,high',
            'color'       => 'nullable|string|in:clear,white,yellow,cloudy',
            'stretch_cm'  => 'nullable|numeric|min:0|max:30',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $logDate = $validated['log_date'] ?? now()->toDateString();
        $consistency = $validated['consistency'];

        // Compute fertility score based on consistency
        $fertilityScore = match ($consistency) {
            'dry'       => 10,
            'sticky'    => 25,
            'creamy'    => 50,
            'watery'    => 75,
            'egg_white' => 100,
            default     => 20,
        };

        // 1. Get or create active MenstrualCycle
        $cycle = MenstrualCycle::where('user_id', $userId)
            ->where('is_completed', false)
            ->latest('period_start_date')
            ->first();

        if (!$cycle) {
            $cycle = MenstrualCycle::create([
                'user_id'           => $userId,
                'period_start_date' => $logDate,
                'is_completed'      => false,
                'prediction_source' => 'mucus',
            ]);
        }

        // 2. Save or update CervicalMucusLog
        $mucusLog = CervicalMucusLog::updateOrCreate(
            [
                'cycle_id' => $cycle->id,
                'log_date' => $logDate,
            ],
            [
                'user_id'         => $userId,
                'cycle_id'        => $cycle->id,
                'consistency'     => $consistency,
                'amount'          => $validated['amount'] ?? null,
                'color'           => $validated['color'] ?? null,
                'stretch_cm'      => $validated['stretch_cm'] ?? null,
                'fertility_score' => $fertilityScore,
                'notes'           => $validated['notes'] ?? null,
            ]
        );

        // 3. Derive Hormone Trends based on Cervical Mucus biological state
        $hormoneTrends = $this->deriveHormoneTrends($consistency, $cycle);

        // 4. Save/update HormoneSnapshot
        try {
            HormoneSnapshot::updateOrCreate(
                [
                    'cycle_id'      => $cycle->id,
                    'snapshot_date' => $logDate,
                ],
                [
                    'estrogen'     => match ($consistency) {
                        'egg_white' => 'peak',
                        'watery'    => 'high',
                        'creamy'    => 'moderate',
                        'sticky'    => 'low',
                        default     => 'very_low',
                    },
                    'progesterone' => match ($consistency) {
                        'dry', 'sticky' => ($cycle->current_phase === 'luteal' ? 'high' : 'low'),
                        'creamy'        => 'moderate',
                        default         => 'low',
                    },
                    'lh'           => match ($consistency) {
                        'egg_white' => 'peak',
                        'watery'    => 'high',
                        default     => 'low',
                    },
                    'modeled'      => true,
                    'source'       => 'phase_model',
                    'note'         => "Derived from Cervical Mucus ({$consistency}) on {$logDate}",
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('HormoneSnapshot update note: ' . $e->getMessage());
        }

        return response()->json([
            'success'        => true,
            'message'        => 'Cervical mucus logged successfully.',
            'data'           => $mucusLog,
            'hormone_trends' => $hormoneTrends,
            'cycle'          => [
                'cycle_id'          => $cycle->id,
                'current_cycle_day' => $cycle->current_cycle_day,
                'current_phase'     => $cycle->current_phase,
            ],
        ], 200);
    }

    /**
     * Get Cervical Mucus log for today or a specific date with hormone trends
     *
     * GET /api/v1/cycle-engine/cervical-mucus/today
     * GET /api/v1/cervical-mucus/today
     */
    public function today(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        $date = $request->input('date') ?? $request->query('date') ?? now()->toDateString();

        $log = CervicalMucusLog::where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereHas('cycle', function ($cq) use ($userId) {
                      $cq->where('user_id', $userId);
                  });
            })
            ->whereDate('log_date', $date)
            ->latest('id')
            ->first();

        $consistency = $log ? $log->consistency : 'creamy';
        $cycle = $log ? $log->cycle : MenstrualCycle::where('user_id', $userId)->latest('id')->first();
        $hormoneTrends = $this->deriveHormoneTrends($consistency, $cycle);

        return response()->json([
            'success'        => true,
            'date'           => $date,
            'logged'         => (bool) $log,
            'data'           => $log,
            'hormone_trends' => $hormoneTrends,
            'all_options'    => CervicalMucusLog::getOptions(),
        ], 200);
    }

    /**
     * Get standalone Hormone Trends endpoint
     *
     * GET /api/v1/cycle-engine/hormone-trends
     * GET /api/v1/hormone-trends
     */
    public function hormoneTrends(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        $date = $request->input('date') ?? $request->query('date') ?? now()->toDateString();

        // Check if user logged Cervical Mucus for this date
        $log = CervicalMucusLog::where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereHas('cycle', function ($cq) use ($userId) {
                      $cq->where('user_id', $userId);
                  });
            })
            ->whereDate('log_date', $date)
            ->latest('id')
            ->first();

        $cycle = MenstrualCycle::where('user_id', $userId)->latest('id')->first();
        $consistency = $log ? $log->consistency : 'egg_white';

        return response()->json([
            'success'        => true,
            'date'           => $date,
            'mucus_logged'   => (bool) $log,
            'consistency'    => $log ? $log->consistency : null,
            'hormone_trends' => $this->deriveHormoneTrends($consistency, $cycle),
        ], 200);
    }

    /**
     * Get Cervical Mucus logs history
     *
     * GET /api/v1/cycle-engine/cervical-mucus/history
     * GET /api/v1/cervical-mucus/history
     */
    public function history(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        $days = (int) ($request->input('days') ?? $request->query('days') ?? 30);

        $query = CervicalMucusLog::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereHas('cycle', function ($cq) use ($userId) {
                  $cq->where('user_id', $userId);
              });
        });

        if ($request->filled('date')) {
            $query->whereDate('log_date', $request->input('date'));
        } elseif ($days > 0) {
            $query->where('log_date', '>=', now()->subDays($days)->toDateString());
        }

        $logs = $query->orderBy('log_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'count'   => $logs->count(),
            'data'    => $logs,
        ], 200);
    }

    /**
     * Get UI Options for Cervical Mucus cards
     *
     * GET /api/v1/cycle-engine/cervical-mucus/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'title'    => 'CERVICAL MUCUS',
            'subtitle' => 'Supports OPK — lowest reliability alone, highest combined. Egg-white = peak fertility signal.',
            'options'  => CervicalMucusLog::getOptions(),
        ], 200);
    }

    /**
     * Derive biological Hormone Trends from Cervical Mucus state
     */
    public function deriveHormoneTrends(string $consistency, ?MenstrualCycle $cycle = null): array
    {
        return match ($consistency) {
            'egg_white' => [
                [
                    'name'          => 'Estrogen (E2)',
                    'value'         => '284 pg/mL',
                    'numeric_value' => 284,
                    'unit'          => 'pg/mL',
                    'status'        => 'Optimal',
                    'status_color'  => 'purple',
                    'percentage'    => 78,
                ],
                [
                    'name'          => 'Progesterone',
                    'value'         => '12.4 ng/mL',
                    'numeric_value' => 12.4,
                    'unit'          => 'ng/mL',
                    'status'        => 'Normal',
                    'status_color'  => 'purple',
                    'percentage'    => 62,
                ],
                [
                    'name'          => 'LH Surge',
                    'value'         => '68 mIU/mL',
                    'numeric_value' => 68,
                    'unit'          => 'mIU/mL',
                    'status'        => 'High',
                    'status_color'  => 'danger',
                    'percentage'    => 85,
                ],
            ],
            'watery' => [
                [
                    'name'          => 'Estrogen (E2)',
                    'value'         => '245 pg/mL',
                    'numeric_value' => 245,
                    'unit'          => 'pg/mL',
                    'status'        => 'Optimal',
                    'status_color'  => 'purple',
                    'percentage'    => 72,
                ],
                [
                    'name'          => 'Progesterone',
                    'value'         => '8.6 ng/mL',
                    'numeric_value' => 8.6,
                    'unit'          => 'ng/mL',
                    'status'        => 'Normal',
                    'status_color'  => 'purple',
                    'percentage'    => 48,
                ],
                [
                    'name'          => 'LH Surge',
                    'value'         => '42 mIU/mL',
                    'numeric_value' => 42,
                    'unit'          => 'mIU/mL',
                    'status'        => 'Rising',
                    'status_color'  => 'warning',
                    'percentage'    => 65,
                ],
            ],
            'creamy' => [
                [
                    'name'          => 'Estrogen (E2)',
                    'value'         => '165 pg/mL',
                    'numeric_value' => 165,
                    'unit'          => 'pg/mL',
                    'status'        => 'Moderate',
                    'status_color'  => 'purple',
                    'percentage'    => 52,
                ],
                [
                    'name'          => 'Progesterone',
                    'value'         => '5.2 ng/mL',
                    'numeric_value' => 5.2,
                    'unit'          => 'ng/mL',
                    'status'        => 'Normal',
                    'status_color'  => 'purple',
                    'percentage'    => 35,
                ],
                [
                    'name'          => 'LH Surge',
                    'value'         => '18 mIU/mL',
                    'numeric_value' => 18,
                    'unit'          => 'mIU/mL',
                    'status'        => 'Baseline',
                    'status_color'  => 'normal',
                    'percentage'    => 28,
                ],
            ],
            'sticky' => [
                [
                    'name'          => 'Estrogen (E2)',
                    'value'         => '110 pg/mL',
                    'numeric_value' => 110,
                    'unit'          => 'pg/mL',
                    'status'        => 'Normal',
                    'status_color'  => 'purple',
                    'percentage'    => 38,
                ],
                [
                    'name'          => 'Progesterone',
                    'value'         => '14.8 ng/mL',
                    'numeric_value' => 14.8,
                    'unit'          => 'ng/mL',
                    'status'        => 'Normal',
                    'status_color'  => 'purple',
                    'percentage'    => 68,
                ],
                [
                    'name'          => 'LH Surge',
                    'value'         => '11 mIU/mL',
                    'numeric_value' => 11,
                    'unit'          => 'mIU/mL',
                    'status'        => 'Baseline',
                    'status_color'  => 'normal',
                    'percentage'    => 20,
                ],
            ],
            'dry' => [
                [
                    'name'          => 'Estrogen (E2)',
                    'value'         => '72 pg/mL',
                    'numeric_value' => 72,
                    'unit'          => 'pg/mL',
                    'status'        => 'Low',
                    'status_color'  => 'purple',
                    'percentage'    => 25,
                ],
                [
                    'name'          => 'Progesterone',
                    'value'         => '16.2 ng/mL',
                    'numeric_value' => 16.2,
                    'unit'          => 'ng/mL',
                    'status'        => 'Elevated',
                    'status_color'  => 'purple',
                    'percentage'    => 75,
                ],
                [
                    'name'          => 'LH Surge',
                    'value'         => '7 mIU/mL',
                    'numeric_value' => 7,
                    'unit'          => 'mIU/mL',
                    'status'        => 'Low',
                    'status_color'  => 'normal',
                    'percentage'    => 15,
                ],
            ],
            default => [
                [
                    'name'          => 'Estrogen (E2)',
                    'value'         => '180 pg/mL',
                    'numeric_value' => 180,
                    'unit'          => 'pg/mL',
                    'status'        => 'Moderate',
                    'status_color'  => 'purple',
                    'percentage'    => 55,
                ],
                [
                    'name'          => 'Progesterone',
                    'value'         => '8.0 ng/mL',
                    'numeric_value' => 8.0,
                    'unit'          => 'ng/mL',
                    'status'        => 'Normal',
                    'status_color'  => 'purple',
                    'percentage'    => 45,
                ],
                [
                    'name'          => 'LH Surge',
                    'value'         => '15 mIU/mL',
                    'numeric_value' => 15,
                    'unit'          => 'mIU/mL',
                    'status'        => 'Baseline',
                    'status_color'  => 'normal',
                    'percentage'    => 25,
                ],
            ],
        };
    }
}
