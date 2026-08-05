<?php

namespace App\Services;

use App\Models\MenstrualCycle;
use App\Models\OpkLog;

class OpkReconciliationService
{
    /**
     * Reconcile OPK logs for a cycle to determine peak day and surge status.
     *
     * @param MenstrualCycle $cycle
     * @return array
     */
    public function reconcileForCycle(MenstrualCycle $cycle): array
    {
        $opkLogs = $cycle->opkLogs()->orderBy('log_date')->get();

        $peakLog = $opkLogs->firstWhere('result', 'peak');
        $positiveLogs = $opkLogs->filter(fn($log) => $log->isPositive());

        $hasSurge = $positiveLogs->isNotEmpty();
        $surgeDay = $peakLog ? $peakLog->log_date->format('Y-m-d') : null;

        return [
            'cycle_id' => $cycle->id,
            'has_surge' => $hasSurge,
            'surge_day' => $surgeDay,
            'total_logs' => $opkLogs->count(),
            'positive_logs_count' => $positiveLogs->count(),
        ];
    }
}
