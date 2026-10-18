<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\MenstrualCycle;
use App\Models\PeriodLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PeriodLogController extends Controller
{
    /**
     * Store or update a period / spotting log entry.
     *
     * Handles:
     * - Store new log if none exists for the date
     * - Update existing log if entry already exists for the date
     * - If `has_spotting` is false or `remove` is true, deletes the entry (as seen in "No spotting" -> Remove entry)
     *
     * POST /api/v1/period/log
     * POST /api/v1/cycle-engine/period/log
     * POST /api/v1/period-logs
     * POST /api/v1/cycle-engine/spotting/log
     */
    public function storeOrUpdate(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->user()?->id ?? $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        // Support aliases 'date' -> 'log_date'
        if (!$request->has('log_date') && $request->has('date')) {
            $request->merge(['log_date' => $request->input('date')]);
        }

        // Normalize color to lowercase if provided
        if ($request->has('color') && is_string($request->input('color'))) {
            $request->merge(['color' => strtolower(trim($request->input('color')))]);
        }

        $validated = $request->validate([
            'log_date'     => ['nullable', 'date'],
            'date'         => ['nullable', 'date'],
            'flow'         => ['nullable', 'string', 'in:spotting,light,medium,heavy'],
            'color'        => ['nullable', 'string', 'in:pink,red,brown'],
            'clotting'     => ['nullable', 'boolean'],
            'cramps'       => ['nullable', 'boolean'],
            'headache'     => ['nullable', 'boolean'],
            'fatigue'      => ['nullable', 'boolean'],
            'pain_level'   => ['nullable', 'integer', 'min:0', 'max:10'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'has_spotting' => ['nullable', 'boolean'],
            'remove'       => ['nullable', 'boolean'],
            'cycle_id'     => ['nullable', 'integer'],
        ]);

        $logDate = !empty($validated['log_date'])
            ? Carbon::parse($validated['log_date'])->toDateString()
            : Carbon::today()->toDateString();

        // If user explicitly chose "No spotting" / remove entry:
        $shouldRemove = ($request->has('has_spotting') && $request->boolean('has_spotting') === false)
            || $request->boolean('remove') === true;

        if ($shouldRemove) {
            return $this->deleteByDate($userId, $logDate);
        }

        // Default flow to 'spotting' if not provided (e.g. from the "Log spotting" modal)
        $flow = $validated['flow'] ?? 'spotting';
        $color = $validated['color'] ?? null;

        // 1. Get or create active MenstrualCycle
        $cycleId = $validated['cycle_id'] ?? null;
        $cycle = null;

        if ($cycleId) {
            $cycle = MenstrualCycle::where('user_id', $userId)->find($cycleId);
        }

        if (!$cycle) {
            $cycle = MenstrualCycle::where('user_id', $userId)
                ->where('is_completed', false)
                ->latest('period_start_date')
                ->first();
        }

        if (!$cycle) {
            $cycle = MenstrualCycle::create([
                'user_id'           => $userId,
                'period_start_date' => $logDate,
                'is_completed'      => false,
                'prediction_source' => 'calendar',
            ]);
        }

        // 2. Check if a log already exists for this user and date
        $existingLog = PeriodLog::where('user_id', $userId)
            ->whereDate('log_date', $logDate)
            ->first();

        $isUpdated = false;

        if ($existingLog) {
            $updateData = [
                'flow'  => $flow,
                'color' => $color ?? $existingLog->color,
                'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $existingLog->notes,
            ];

            if (array_key_exists('clotting', $validated)) {
                $updateData['clotting'] = (bool) $validated['clotting'];
            }
            if (array_key_exists('cramps', $validated)) {
                $updateData['cramps'] = (bool) $validated['cramps'];
            }
            if (array_key_exists('headache', $validated)) {
                $updateData['headache'] = (bool) $validated['headache'];
            }
            if (array_key_exists('fatigue', $validated)) {
                $updateData['fatigue'] = (bool) $validated['fatigue'];
            }
            if (array_key_exists('pain_level', $validated)) {
                $updateData['pain_level'] = $validated['pain_level'];
            }

            $existingLog->update($updateData);
            $periodLog = $existingLog->fresh();
            $isUpdated = true;
        } else {
            $periodLog = PeriodLog::create([
                'user_id'    => $userId,
                'cycle_id'   => $cycle->id,
                'log_date'   => $logDate,
                'flow'       => $flow,
                'color'      => $color,
                'clotting'   => (bool) ($validated['clotting'] ?? false),
                'cramps'     => (bool) ($validated['cramps'] ?? false),
                'headache'   => (bool) ($validated['headache'] ?? false),
                'fatigue'    => (bool) ($validated['fatigue'] ?? false),
                'pain_level' => $validated['pain_level'] ?? null,
                'notes'      => $validated['notes'] ?? null,
            ]);
        }

        // 3. Trigger AI Engine cycle sync if available
        $this->syncAiEngine();

        return response()->json([
            'success' => true,
            'message' => $isUpdated
                ? 'Period/spotting log updated successfully.'
                : 'Period/spotting log created successfully.',
            'data'    => $periodLog,
        ], $isUpdated ? 200 : 201);
    }

    /**
     * Get period / spotting log entry or list.
     *
     * GET /api/v1/period/log?date=YYYY-MM-DD
     * GET /api/v1/period-logs?date=YYYY-MM-DD
     * GET /api/v1/period-logs?month=YYYY-MM
     * GET /api/v1/period-logs
     * GET /api/v1/period-logs/{id}
     */
    public function getLog(Request $request, $id = null): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->user()?->id ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        // 1. Fetch by explicit ID
        $logId = $id ?? $request->query('id');
        if ($logId) {
            $log = PeriodLog::where('user_id', $userId)->find($logId);
            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Period log not found.',
                    'data'    => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $log,
            ]);
        }

        // 2. Fetch single entry by specific date
        $dateParam = $request->query('date') ?? $request->query('log_date');
        if ($dateParam) {
            $parsedDate = Carbon::parse($dateParam)->toDateString();
            $log = PeriodLog::where('user_id', $userId)
                ->whereDate('log_date', $parsedDate)
                ->first();

            return response()->json([
                'success' => true,
                'data'    => $log,
                'message' => $log ? null : 'No period/spotting log found for this date.',
            ]);
        }

        // 3. Query logs with optional month / date range filtering
        $query = PeriodLog::where('user_id', $userId)->orderByDesc('log_date');

        if ($request->filled('month')) {
            $month = Carbon::parse($request->query('month'));
            $query->whereYear('log_date', $month->year)
                  ->whereMonth('log_date', $month->month);
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('log_date', [
                Carbon::parse($request->query('start_date'))->toDateString(),
                Carbon::parse($request->query('end_date'))->toDateString(),
            ]);
        } elseif ($request->filled('cycle_id')) {
            $query->where('cycle_id', $request->query('cycle_id'));
        }

        if ($request->filled('flow')) {
            $query->where('flow', strtolower(trim($request->query('flow'))));
        }

        $logs = $query->limit(100)->get();

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * Show a single period log by ID.
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->getLog($request, $id);
    }

    /**
     * Delete a period / spotting log entry.
     *
     * Supports:
     * - DELETE /api/v1/period-logs/{id}
     * - DELETE /api/v1/period/log?date=YYYY-MM-DD
     * - DELETE /api/v1/period-logs?date=YYYY-MM-DD
     * - POST /api/v1/period/log/delete (with { date: '...' } or { id: ... })
     */
    public function destroy(Request $request, $id = null): JsonResponse
    {
        $userId = auth('sanctum')->id() ?? $request->user()?->id ?? $request->input('user_id') ?? $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        $logId = $id ?? $request->input('id') ?? $request->query('id');

        if ($logId) {
            $log = PeriodLog::where('user_id', $userId)->find($logId);
            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Period log not found.',
                ], 404);
            }

            $log->delete();
            $this->syncAiEngine();

            return response()->json([
                'success' => true,
                'message' => 'Period/spotting log removed successfully.',
            ]);
        }

        $dateParam = $request->input('date') ?? $request->input('log_date') ?? $request->query('date') ?? $request->query('log_date');

        if ($dateParam) {
            $parsedDate = Carbon::parse($dateParam)->toDateString();
            return $this->deleteByDate($userId, $parsedDate);
        }

        return response()->json([
            'success' => false,
            'message' => 'Please provide an entry id or date to delete.',
        ], 422);
    }

    /**
     * Internal helper to delete by user ID and date.
     */
    protected function deleteByDate(int $userId, string $date): JsonResponse
    {
        $deleted = PeriodLog::where('user_id', $userId)
            ->whereDate('log_date', $date)
            ->delete();

        if ($deleted) {
            $this->syncAiEngine();

            return response()->json([
                'success' => true,
                'message' => 'Spotting/period entry removed successfully.',
                'data'    => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'No spotting/period entry found to remove for this date.',
            'data'    => null,
        ]);
    }

    /**
     * Internal helper to trigger AI sync safely in background without blocking response.
     */
    protected function syncAiEngine(): void
    {
        defer(function () {
            try {
                if (class_exists(\App\Http\Controllers\AI\CycleSummaryController::class)) {
                    app(\App\Http\Controllers\AI\CycleSummaryController::class)->sync();
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to auto-sync AI engine after period log: " . $e->getMessage());
            }
        });
    }
}
