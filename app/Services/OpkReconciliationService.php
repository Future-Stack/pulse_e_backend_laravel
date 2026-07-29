<?php

namespace App\Services;

use App\Models\MenstrualCycle;
use App\Models\OpkLog;
use Carbon\Carbon;

class OpkReconciliationService
{
    /**
     * Evaluate testing window for given date/cycle
     */
    public function evaluateWindow(MenstrualCycle $cycle, string $date): array
    {
        $logDate   = Carbon::parse($date);
        $startDate = Carbon::parse($cycle->period_start_date);
        
        // Cycle Day Calculation (Day 1, Day 2, ...)
        $cycleDay  = $startDate->diffInDays($logDate) + 1;

        // Use database columns if set, fallback to default (Day 10 - Day 15)
        $windowStart = $cycle->fertile_start_day ?? (($cycle->predicted_ovulation_day ?? 14) - 4);
        $windowEnd   = $cycle->fertile_end_day ?? (($cycle->predicted_ovulation_day ?? 14) + 1);

        $outsideWindow = $cycleDay < $windowStart || $cycleDay > $windowEnd;

        $windowStatus = 'closed';
        if ($cycleDay >= $windowStart && $cycleDay <= $windowEnd) {
            $windowStatus = 'open';
        } elseif ($cycleDay < $windowStart) {
            $windowStatus = 'upcoming';
        }

        return [
            'cycle_day'        => $cycleDay,
            'window_start_day' => $windowStart,
            'window_end_day'   => $windowEnd,
            'outside_window'   => $outsideWindow,
            'window_status'    => $windowStatus,
        ];
    }

    /**
     * Build full reconciliation data
     */
    public function build(MenstrualCycle $cycle, string $date): array
    {
        $calendarPredictedDay = $cycle->predicted_ovulation_day ?? 14;
        $bbtConfirmedDay      = $cycle->confirmed_ovulation_day;
        
        // Check if there is an OPK peak recorded in this cycle
        $opkPeakLog = OpkLog::where('cycle_id', $cycle->id)
            ->where('result', 'peak')
            ->first();

        $lhSurgeDay = null;
        if ($opkPeakLog) {
            $startDate  = Carbon::parse($cycle->period_start_date);
            $lhSurgeDay = $startDate->diffInDays(Carbon::parse($opkPeakLog->log_date)) + 1;
        }

        $finalConfirmedDay = $bbtConfirmedDay ?? $lhSurgeDay ?? $calendarPredictedDay;
        
        $finalSource = $cycle->prediction_source;
        if ($bbtConfirmedDay) {
            $finalSource = 'bbt';
        } elseif ($lhSurgeDay) {
            $finalSource = 'opk';
        }

        return [
            'user_id'                => $cycle->user_id,
            'cycle_id'               => $cycle->id,
            'calendar_predicted_day' => $calendarPredictedDay,
            'bbt_confirmed_day'      => $bbtConfirmedDay,
            'lh_surge_day'           => $lhSurgeDay,
            'final_confirmed_day'    => $finalConfirmedDay,
            'final_source'           => $finalSource,
            'offset_days'            => abs($finalConfirmedDay - $calendarPredictedDay),
            'luteal_phase_length'    => 14,
        ];
    }

    /**
     * Get UI strip logs (D10 to D15 or fertile window range)
     */
    public function getWindowStripLogs(MenstrualCycle $cycle): array
    {
        $startDate   = Carbon::parse($cycle->period_start_date);
        $windowStart = $cycle->fertile_start_day ?? 10;
        $windowEnd   = $cycle->fertile_end_day ?? 15;

        $logs = OpkLog::where('cycle_id', $cycle->id)
            ->get()
            ->keyBy(fn($item) => Carbon::parse($item->log_date)->toDateString());

        $strip = [];
        for ($day = $windowStart; $day <= $windowEnd; $day++) {
            $currentDate = $startDate->copy()->addDays($day - 1)->toDateString();
            $log = $logs->get($currentDate);

            $symbol = '?';
            if ($log) {
                $symbol = match ($log->result) {
                    'positive', 'peak' => '+',
                    'negative'         => '-',
                    default            => '?'
                };
            }

            $strip[] = [
                'day_label' => 'D' . $day,
                'cycle_day' => $day,
                'date'      => $currentDate,
                'result'    => $log->result ?? null,
                'symbol'    => $symbol,
            ];
        }

        return $strip;
    }
}