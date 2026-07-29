<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\OpkLog;
use App\Models\MenstrualCycle;
use App\Services\OpkReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OpkLogController extends Controller
{
    public function __construct(protected OpkReconciliationService $reconciliation)
    {
    }

    /**
     * GET /api/v1/cycle-engine/opk/testing-window
     */
    public function testingWindow(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->whereDate('period_start_date', '<=', $today)
            ->latest('period_start_date')
            ->first();

        if (!$cycle) {
            return response()->json(['message' => 'No active cycle found.'], 404);
        }

        $windowInfo = $this->reconciliation->evaluateWindow($cycle, $today);
        $stripLogs  = $this->reconciliation->getWindowStripLogs($cycle);

        return response()->json([
            'window_start_day'   => $windowInfo['window_start_day'],
            'window_end_day'     => $windowInfo['window_end_day'],
            'predicted_peak_day' => $cycle->predicted_ovulation_day ?? 14,
            'window_status'      => $windowInfo['window_status'],
            'window_strip'       => $stripLogs,
        ]);
    }

    /**
     * POST /api/v1/cycle-engine/opk/log
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();
        if (isset($input['date']) && !isset($input['log_date'])) {
            $input['log_date'] = $input['date'];
        }

        $validated = validator($input, [
            'log_date' => ['required', 'date'],
            'result'   => ['required', 'in:negative,positive,peak'],
            'lh_value' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'note'     => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $user    = Auth::user();
        $logDate = $validated['log_date'];

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->whereDate('period_start_date', '<=', $logDate)
            ->latest('period_start_date')
            ->first();

        if (!$cycle) {
            return response()->json([
                'stored'  => false,
                'message' => 'No active cycle found for this user/date.',
            ], 422);
        }

        $windowInfo        = $this->reconciliation->evaluateWindow($cycle, $logDate);
        $outsideWindow     = $windowInfo['outside_window'];
        $affectsPrediction = !$outsideWindow && in_array($validated['result'], ['positive', 'peak'], true);

        $note = $validated['note'] ?? $this->defaultNote($validated['result']);

        $log = OpkLog::updateOrCreate(
            [
                'cycle_id' => $cycle->id,
                'log_date' => $logDate,
            ],
            [
                'result'             => $validated['result'],
                'lh_value'           => $validated['lh_value'] ?? 0,
                'outside_window'     => $outsideWindow,
                'affects_prediction' => $affectsPrediction,
                'note'               => $note,
            ]
        );

        // Update prediction source if peak is logged
        if ($validated['result'] === 'peak') {
            $cycle->update([
                'prediction_source' => 'opk'
            ]);
        }

        $actNow = ($validated['result'] === 'peak') && !$outsideWindow;

        return response()->json([
            'stored' => true,
            'log' => [
                'id'                 => $log->id,
                'user_id'            => $user->id,
                'date'               => Carbon::parse($log->log_date)->toDateString(),
                'result'             => $log->result,
                'lh_value'           => (float) $log->lh_value,
                'note'               => $log->note,
                'outside_window'     => (bool) $log->outside_window,
                'affects_prediction' => (bool) $log->affects_prediction,
            ],
            'outside_window'     => (bool) $outsideWindow,
            'affects_prediction' => (bool) $affectsPrediction,
            'act_now'            => $actNow,
            'reconciliation'     => $this->reconciliation->build($cycle, $logDate),
        ]);
    }

    /**
     * GET /api/v1/cycle-engine/opk/today-status
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        $cycle = MenstrualCycle::where('user_id', $user->id)
            ->whereDate('period_start_date', '<=', $today)
            ->latest('period_start_date')
            ->first();

        $log = null;
        if ($cycle) {
            $log = OpkLog::where('cycle_id', $cycle->id)
                ->whereDate('log_date', $today)
                ->first();
        }

        $outsideWindow = true;
        $windowStatus  = 'unknown';
        if ($cycle) {
            $windowInfo    = $this->reconciliation->evaluateWindow($cycle, $today);
            $outsideWindow = $windowInfo['outside_window'];
            $windowStatus  = $outsideWindow ? 'outside_fertile_window' : 'in_window';
        }

        return response()->json([
            'date'           => $today,
            'logged'         => (bool) $log,
            'result'         => $log->result ?? null,
            'lh_value'       => $log ? (float) $log->lh_value : null,
            'note'           => $log->note ?? null,
            'outside_window' => $outsideWindow,
            'window_status'  => $windowStatus,
        ]);
    }

    protected function defaultNote(string $result): string
    {
        return match ($result) {
            'peak'     => 'LH surge detected',
            'positive' => 'LH elevated',
            default    => 'LH not elevated',
        };
    }
}