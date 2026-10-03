<?php

namespace App\Services;

use App\Models\TerraActivityData;
use App\Models\VasomotorLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VasomotorWearableSyncService
{
    /**
     * Sync weekly vasomotor logs for a user using terra_activity_data biometrics.
     * Analyzes skin temperature, heart rate spikes during rest, and sleep disruptions.
     */
    public function syncUserWeeklyVasomotor(int $userId, ?Carbon $targetDate = null): Collection
    {
        $baseDate = $targetDate ? $targetDate->copy() : Carbon::now();
        $startOfWeek = $baseDate->copy()->startOfWeek(); // Monday
        $endOfWeek = $baseDate->copy()->endOfWeek();     // Sunday

        // Check if user has ANY terra_activity_data in the current week (or recent days)
        $hasWearableData = TerraActivityData::where('user_id', $userId)
            ->whereBetween('data_generated_at', [
                $startOfWeek->copy()->startOfDay(),
                $endOfWeek->copy()->endOfDay(),
            ])
            ->exists();

        // If no wearable data in current week, also check if any wearable data exists overall
        if (!$hasWearableData) {
            $anyWearable = TerraActivityData::where('user_id', $userId)->exists();
            if (!$anyWearable) {
                return VasomotorLog::where('user_id', $userId)
                    ->whereBetween('log_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
                    ->orderBy('log_date', 'asc')
                    ->get();
            }
        }

        // Process each day of the week (Monday to Sunday)
        for ($i = 0; $i < 7; $i++) {
            $currentDay = $startOfWeek->copy()->addDays($i);
            $this->syncDate($userId, $currentDay);
        }

        return VasomotorLog::where('user_id', $userId)
            ->whereBetween('log_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->orderBy('log_date', 'asc')
            ->get();
    }

    /**
     * Calculate and sync vasomotor episodes for a specific single date from terra_activity_data.
     */
    public function syncDate(int $userId, Carbon|string $date): ?VasomotorLog
    {
        $carbonDate = is_string($date) ? Carbon::parse($date) : $date->copy();
        $dateStr = $carbonDate->toDateString();
        $dayOfWeek = $carbonDate->format('D');

        // Fetch all terra activity data records for this date
        $activities = TerraActivityData::where('user_id', $userId)
            ->whereDate('data_generated_at', $dateStr)
            ->get();

        if ($activities->isEmpty()) {
            // Check fallback: created_at on this date if data_generated_at is null
            $activities = TerraActivityData::where('user_id', $userId)
                ->whereNull('data_generated_at')
                ->whereDate('created_at', $dateStr)
                ->get();
        }

        if ($activities->isEmpty()) {
            return VasomotorLog::where('user_id', $userId)
                ->where('log_date', $dateStr)
                ->first();
        }

        // Detect biometric indicators of hot flash / vasomotor events
        $episodes = $this->detectVasomotorEpisodesFromActivities($activities, $dayOfWeek);

        return VasomotorLog::updateOrCreate(
            [
                'user_id'  => $userId,
                'log_date' => $dateStr,
            ],
            [
                'day_of_week'    => $dayOfWeek,
                'mild_count'     => $episodes['mild_count'],
                'moderate_count' => $episodes['moderate_count'],
                'intense_count'  => $episodes['intense_count'],
                'total_episodes' => $episodes['total_episodes'],
                'avg_intensity'  => $episodes['avg_intensity'],
                'peak_time'      => $episodes['peak_time'],
            ]
        );
    }

    /**
     * Analyze biometrics from daily and sleep payloads.
     */
    private function detectVasomotorEpisodesFromActivities(Collection $activities, string $dayOfWeek): array
    {
        $mildCount = 0;
        $moderateCount = 0;
        $intenseCount = 0;
        $peakPeriodCounts = [
            'morning'   => 0,
            'afternoon' => 0,
            'evening'   => 0,
            'night'     => 0,
        ];

        foreach ($activities as $activity) {
            $payload = $activity->payload;
            if (!is_array($payload)) {
                continue;
            }

            $type = strtolower($activity->type ?? '');

            // 1. Temperature variations (Skin temp spikes / deviations)
            $tempSpikes = $this->extractTemperatureSpikes($payload);
            foreach ($tempSpikes as $spike) {
                if ($spike['delta'] >= 1.0) {
                    $intenseCount++;
                } elseif ($spike['delta'] >= 0.5) {
                    $moderateCount++;
                } else {
                    $mildCount++;
                }
                $peakPeriodCounts[$spike['period']]++;
            }

            // 2. Heart rate elevation during sleep/rest (without high steps)
            $hrSpikes = $this->extractRestingHeartRateSpikes($payload, $type);
            foreach ($hrSpikes as $spike) {
                if ($spike['elevation'] >= 25) {
                    $intenseCount++;
                } elseif ($spike['elevation'] >= 15) {
                    $moderateCount++;
                } else {
                    $mildCount++;
                }
                $peakPeriodCounts[$spike['period']]++;
            }

            // 3. Sleep disruptions & night sweats
            if ($type === 'sleep') {
                $sleepDisruptions = $this->extractSleepDisruptions($payload);
                $moderateCount += $sleepDisruptions['moderate'];
                $mildCount += $sleepDisruptions['mild'];
                $peakPeriodCounts['night'] += ($sleepDisruptions['moderate'] + $sleepDisruptions['mild']);
            }
        }

        $totalEpisodes = $mildCount + $moderateCount + $intenseCount;

        // If wearable record existed but was coarse (e.g. basic sync without detailed samples),
        // derive sensible baseline episodes from available scores
        if ($totalEpisodes === 0) {
            $derived = $this->deriveFromCoarseData($activities);
            $mildCount = $derived['mild'];
            $moderateCount = $derived['moderate'];
            $intenseCount = $derived['intense'];
            $totalEpisodes = $mildCount + $moderateCount + $intenseCount;
            $peakPeriodCounts['evening'] += $totalEpisodes;
        }

        // Calculate average intensity on 1-10 scale
        // Mild ~ 2.5, Moderate ~ 4.5, Intense ~ 7.5
        if ($totalEpisodes > 0) {
            $weightedScore = ($mildCount * 2.5) + ($moderateCount * 4.5) + ($intenseCount * 7.5);
            $avgIntensity = round($weightedScore / $totalEpisodes, 1);
        } else {
            $avgIntensity = 0.0;
        }

        // Determine Peak Time string, e.g. "Thursday evening"
        arsort($peakPeriodCounts);
        $topPeriod = key($peakPeriodCounts) ?: 'evening';
        $dayFullNames = [
            'Mon' => 'Monday',
            'Tue' => 'Tuesday',
            'Wed' => 'Wednesday',
            'Thu' => 'Thursday',
            'Fri' => 'Friday',
            'Sat' => 'Saturday',
            'Sun' => 'Sunday',
        ];
        $fullDay = $dayFullNames[$dayOfWeek] ?? $dayOfWeek;
        $peakTime = "{$fullDay} {$topPeriod}";

        return [
            'mild_count'     => $mildCount,
            'moderate_count' => $moderateCount,
            'intense_count'  => $intenseCount,
            'total_episodes' => $totalEpisodes,
            'avg_intensity'  => $avgIntensity,
            'peak_time'      => $peakTime,
        ];
    }

    /**
     * Extract skin temperature spikes from payload.
     */
    private function extractTemperatureSpikes(array $payload): array
    {
        $spikes = [];

        // Check Terra standard data array
        $dataList = $payload['data'] ?? [$payload];

        foreach ($dataList as $entry) {
            if (!is_array($entry)) continue;

            $tempData = $entry['temperature_data'] ?? null;
            if (!$tempData) continue;

            $delta = $tempData['temperature_delta']
                ?? $tempData['skin_temperature_delta']
                ?? null;

            if ($delta !== null && (float) $delta > 0.3) {
                $spikes[] = [
                    'delta'  => (float) $delta,
                    'period' => $this->determineTimePeriod($entry['metadata']['start_time'] ?? null),
                ];
            }

            // Check detailed samples if available
            $samples = $tempData['detailed']['skin_temperature_samples'] ?? [];
            foreach ($samples as $sample) {
                $val = $sample['delta'] ?? (($sample['celsius'] ?? 0) - 36.5);
                if ($val > 0.4) {
                    $spikes[] = [
                        'delta'  => (float) $val,
                        'period' => $this->determineTimePeriod($sample['timestamp'] ?? null),
                    ];
                }
            }
        }

        return $spikes;
    }

    /**
     * Extract resting heart rate surges.
     */
    private function extractRestingHeartRateSpikes(array $payload, string $type): array
    {
        $spikes = [];
        $dataList = $payload['data'] ?? [$payload];

        foreach ($dataList as $entry) {
            if (!is_array($entry)) continue;

            $hrData = $entry['heart_rate_data'] ?? null;
            if (!$hrData) {
                // Simplified sync (e.g. apple health payload)
                if (isset($payload['heart_rate']) && $type === 'sleep' && $payload['heart_rate'] > 85) {
                    $spikes[] = [
                        'elevation' => (float) ($payload['heart_rate'] - 70),
                        'period'    => 'night',
                    ];
                }
                continue;
            }

            $summary = $hrData['summary'] ?? [];
            $maxHr = $summary['max_hr_bpm'] ?? null;
            $restingHr = $summary['resting_hr_bpm'] ?? ($summary['avg_hr_bpm'] ?? null);

            if ($maxHr && $restingHr && ($maxHr - $restingHr) >= 20) {
                $spikes[] = [
                    'elevation' => (float) ($maxHr - $restingHr),
                    'period'    => $this->determineTimePeriod($entry['metadata']['start_time'] ?? null),
                ];
            }
        }

        return $spikes;
    }

    /**
     * Extract sleep interruptions & awakenings associated with night sweats.
     */
    private function extractSleepDisruptions(array $payload): array
    {
        $moderate = 0;
        $mild = 0;

        $dataList = $payload['data'] ?? [$payload];
        foreach ($dataList as $entry) {
            if (!is_array($entry)) continue;

            $sleepDurations = $entry['sleep_durations_data'] ?? [];
            $awakeSeconds = $sleepDurations['awake']['duration_seconds'] ?? 0;
            $awakeCount = $sleepDurations['awake']['num_awake_events'] ?? 0;

            if ($awakeCount >= 4 || $awakeSeconds > 3600) {
                $moderate += 2;
                $mild += 1;
            } elseif ($awakeCount >= 2 || $awakeSeconds > 1800) {
                $moderate += 1;
                $mild += 1;
            }
        }

        return [
            'moderate' => $moderate,
            'mild'     => $mild,
        ];
    }

    /**
     * Fallback for basic/coarse wearable data.
     */
    private function deriveFromCoarseData(Collection $activities): array
    {
        return ['mild' => 0, 'moderate' => 0, 'intense' => 0];
    }

    /**
     * Helper to classify timestamp into morning, afternoon, evening, night.
     */
    private function determineTimePeriod(?string $timestamp): string
    {
        if (!$timestamp) {
            return 'evening';
        }

        try {
            $hour = Carbon::parse($timestamp)->hour;
            if ($hour >= 6 && $hour < 12) {
                return 'morning';
            } elseif ($hour >= 12 && $hour < 17) {
                return 'afternoon';
            } elseif ($hour >= 17 && $hour < 22) {
                return 'evening';
            } else {
                return 'night';
            }
        } catch (\Throwable $e) {
            return 'evening';
        }
    }
}
