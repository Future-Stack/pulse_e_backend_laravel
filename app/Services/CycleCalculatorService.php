<?php

namespace App\Services;

use App\Models\CycleCalendarInput;
use App\Models\CycleSetting;
use App\Models\CycleStatistic;
use App\Models\MenstrualCycle;
use App\Models\User;
use Carbon\Carbon;

class CycleCalculatorService
{
    /**
     * Resolve the user's cycle settings dynamically.
     *
     * Priority:
     * 1. Historical CycleCalendarInput start dates
     * 2. Recent MenstrualCycle
     * 3. CycleStatistic
     * 4. Explicit CycleSetting
     * 5. Default 28 days
     *
     * Cycle length is calculated from:
     *
     * Previous period START date -> Next period START date
     *
     * Example:
     *
     * Sep 10 -> Oct 11 = 31 days
     * Oct 11 -> Nov 12 = 32 days
     */
    public static function getUserCycleSettings(int|User $user): array
    {
        $userId = $user instanceof User
            ? $user->id
            : (int) $user;

        /*
        |--------------------------------------------------------------------------
        | Cycle Setting
        |--------------------------------------------------------------------------
        */
        $setting = CycleSetting::where('user_id', $userId)->first();

        /*
        |--------------------------------------------------------------------------
        | Default Values
        |--------------------------------------------------------------------------
        */
        $cycleLength = null;

        $lutealLength = $setting?->luteal_phase_length ?? 14;

        $periodLength = $setting?->average_period_length ?? 5;

        $cycleHistory = [];

        /*
        |--------------------------------------------------------------------------
        | 1. Historical Cycle Calendar Inputs
        |--------------------------------------------------------------------------
        |
        | Get all recorded period start dates.
        |
        | Example:
        |
        | C1:
        | Sep 10 -> Oct 11 = 31 days
        |
        | C2:
        | Oct 11 -> Nov 12 = 32 days
        |
        |--------------------------------------------------------------------------
        */
        $inputs = CycleCalendarInput::where('user_id', $userId)
            ->whereNotNull('start_date')
            ->orderBy('start_date', 'asc')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Build Cycle History
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Cycle number is based on valid cycle intervals, NOT database IDs.
        |
        | Example:
        |
        | DB IDs:
        | 2, 4, 7
        |
        | API cycle names:
        | C1, C2
        |
        |--------------------------------------------------------------------------
        */
        if ($inputs->count() >= 2) {

            $cycleNumber = 1;

            for ($i = 1; $i < $inputs->count(); $i++) {

                $previousStart = Carbon::parse(
                    $inputs[$i - 1]->start_date
                );

                $currentStart = Carbon::parse(
                    $inputs[$i]->start_date
                );

                /*
                |--------------------------------------------------------------------------
                | Calculate Cycle Length
                |--------------------------------------------------------------------------
                */
                $diff = $previousStart->diffInDays(
                    $currentStart
                );

                /*
                |--------------------------------------------------------------------------
                | Accept Reasonable Cycle Lengths Only
                |--------------------------------------------------------------------------
                |
                | 20-50 days supports reasonable cycle variation
                | while filtering invalid records.
                |
                |--------------------------------------------------------------------------
                */
                if ($diff >= 20 && $diff <= 50) {

                    $cycleHistory[] = [
                        'cycle' => 'C' . $cycleNumber,

                        'start_date' => $previousStart->format('Y-m-d'),

                        'next_start_date' => $currentStart->format('Y-m-d'),

                        'cycle_length' => $diff,
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | Increment ONLY after a valid cycle is added.
                    |--------------------------------------------------------------------------
                    */
                    $cycleNumber++;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate Average Cycle Length From History
        |--------------------------------------------------------------------------
        */
        if (!empty($cycleHistory)) {

            $historyLengths = collect($cycleHistory)
                ->pluck('cycle_length')
                ->map(fn ($length) => (int) $length)
                ->values();

            $cycleLength = (int) round(
                $historyLengths->avg()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Recent Menstrual Cycle
        |--------------------------------------------------------------------------
        |
        | Used only when historical period start dates are not enough.
        |--------------------------------------------------------------------------
        */
        if (!$cycleLength) {

            $recentCycle = MenstrualCycle::where(
                'user_id',
                $userId
            )
                ->whereNotNull('cycle_length')
                ->latest('id')
                ->first();

            if (
                $recentCycle &&
                $recentCycle->cycle_length >= 20 &&
                $recentCycle->cycle_length <= 50
            ) {
                $cycleLength = (int) $recentCycle->cycle_length;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Cycle Statistic
        |--------------------------------------------------------------------------
        */
        if (!$cycleLength) {

            $statistic = CycleStatistic::where(
                'user_id',
                $userId
            )->first();

            if (
                $statistic &&
                $statistic->average_cycle_length >= 20 &&
                $statistic->average_cycle_length <= 50
            ) {
                $cycleLength = (int) $statistic->average_cycle_length;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Explicit Cycle Setting
        |--------------------------------------------------------------------------
        |
        | Used only when no usable historical/statistical
        | cycle data exists.
        |--------------------------------------------------------------------------
        */
        if (
            !$cycleLength &&
            $setting &&
            !empty($setting->average_cycle_length)
        ) {

            $configuredCycleLength = (int) $setting->average_cycle_length;

            if (
                $configuredCycleLength >= 20 &&
                $configuredCycleLength <= 50
            ) {
                $cycleLength = $configuredCycleLength;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Default Fallback
        |--------------------------------------------------------------------------
        */
        $cycleLength = $cycleLength ?: 28;

        /*
        |--------------------------------------------------------------------------
        | Safety Boundaries
        |--------------------------------------------------------------------------
        */
        $cycleLength = max(
            20,
            min(50, $cycleLength)
        );

        $periodLength = max(
            2,
            min(10, (int) $periodLength)
        );

        /*
        |--------------------------------------------------------------------------
        | Make Sure Luteal Phase Fits Inside Cycle
        |--------------------------------------------------------------------------
        */
        $maxLuteal = max(
            8,
            $cycleLength - $periodLength - 2
        );

        $lutealLength = max(
            8,
            min(
                (int) $lutealLength,
                $maxLuteal
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Return Cycle Settings
        |--------------------------------------------------------------------------
        */
        return [
            'cycle_length' => $cycleLength,

            'luteal_phase_length' => $lutealLength,

            'period_length' => $periodLength,

            'is_custom' => $cycleLength !== 28,

            'cycle_history' => $cycleHistory,
        ];
    }

    /**
     * Calculate cycle phase boundaries dynamically.
     */
    public static function calculatePhases(
        int $cycleLength,
        int $lutealLength = 14,
        int $periodLength = 5
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Safety Boundaries
        |--------------------------------------------------------------------------
        */
        $cycleLength = max(
            20,
            min(50, $cycleLength)
        );

        $periodLength = max(
            2,
            min(10, $periodLength)
        );

        /*
        |--------------------------------------------------------------------------
        | Make Sure Luteal Phase Fits Inside Cycle
        |--------------------------------------------------------------------------
        */
        $maxLuteal = max(
            8,
            $cycleLength - $periodLength - 2
        );

        $lutealLength = max(
            8,
            min(
                $lutealLength,
                $maxLuteal
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Estimated Ovulation Day
        |--------------------------------------------------------------------------
        |
        | Formula:
        |
        | Ovulation Day = Cycle Length - Luteal Length
        |
        | Examples:
        |
        | 26 - 14 = Day 12
        | 28 - 14 = Day 14
        | 31 - 14 = Day 17
        | 32 - 14 = Day 18
        |--------------------------------------------------------------------------
        */
        $ovulationDay = max(
            $periodLength + 2,
            $cycleLength - $lutealLength
        );

        /*
        |--------------------------------------------------------------------------
        | Menstrual Phase
        |--------------------------------------------------------------------------
        */
        $menstrualStart = 1;

        $menstrualEnd = $periodLength;

        /*
        |--------------------------------------------------------------------------
        | Fertile Window
        |--------------------------------------------------------------------------
        */
        $fertileStart = max(
            $periodLength + 1,
            $ovulationDay - 5
        );

        /*
        |--------------------------------------------------------------------------
        | Ovulatory Window
        |--------------------------------------------------------------------------
        */
        $ovulatoryStart = max(
            $periodLength + 1,
            $ovulationDay - 1
        );

        $ovulatoryEnd = min(
            $cycleLength,
            $ovulationDay + 1
        );

        $fertileEnd = $ovulatoryEnd;

        /*
        |--------------------------------------------------------------------------
        | Follicular Phase
        |--------------------------------------------------------------------------
        */
        $follicularStart = $periodLength + 1;

        $follicularEnd = max(
            $follicularStart,
            $ovulatoryStart - 1
        );

        /*
        |--------------------------------------------------------------------------
        | Luteal Phase
        |--------------------------------------------------------------------------
        */
        $lutealStart = $ovulatoryEnd + 1;

        $lutealEnd = $cycleLength;

        /*
        |--------------------------------------------------------------------------
        | Return Phase Ranges
        |--------------------------------------------------------------------------
        */
        return [
            'cycle_length' => $cycleLength,

            'period_length' => $periodLength,

            'luteal_length' => $lutealLength,

            'ovulation_day' => $ovulationDay,

            'menstrual_start' => $menstrualStart,

            'menstrual_end' => $menstrualEnd,

            'follicular_start' => $follicularStart,

            'follicular_end' => $follicularEnd,

            'ovulatory_start' => $ovulatoryStart,

            'ovulatory_end' => $ovulatoryEnd,

            'fertile_start' => $fertileStart,

            'fertile_end' => $fertileEnd,

            'luteal_start' => $lutealStart,

            'luteal_end' => $lutealEnd,
        ];
    }

    /**
     * Determine phase information for a specific cycle day.
     */
    public static function getPhaseForCycleDay(
        int $cycleDay,
        int $cycleLength,
        int $lutealLength = 14,
        int $periodLength = 5,
        bool $isPeriodLogged = false
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Calculate Phase Boundaries
        |--------------------------------------------------------------------------
        */
        $phases = self::calculatePhases(
            $cycleLength,
            $lutealLength,
            $periodLength
        );

        /*
        |--------------------------------------------------------------------------
        | Keep Cycle Day Inside Cycle Boundaries
        |--------------------------------------------------------------------------
        */
        $cycleDay = max(
            1,
            min(
                $cycleDay,
                $cycleLength
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Determine Current Phase
        |--------------------------------------------------------------------------
        */
        if (
            $cycleDay <= $phases['menstrual_end'] ||
            $isPeriodLogged
        ) {

            $phaseName = 'Menstrual Phase';

            $phaseKey = 'menstrual';

            $color = 'red';

            $icon = '🔴';

        } elseif (
            $cycleDay <= $phases['follicular_end']
        ) {

            $phaseName = 'Follicular Phase';

            $phaseKey = 'follicular';

            $color = 'green';

            $icon = '🟢';

        } elseif (
            $cycleDay <= $phases['ovulatory_end']
        ) {

            $phaseName = 'Ovulatory Phase';

            $phaseKey = 'ovulatory';

            $color = 'yellow';

            $icon = '🟡';

        } else {

            $phaseName = 'Luteal Phase';

            $phaseKey = 'luteal';

            $color = 'blue';

            $icon = '🔵';
        }

        /*
        |--------------------------------------------------------------------------
        | Calendar Status
        |--------------------------------------------------------------------------
        */
        $calendarStatus = [

            'is_period' => (
                $isPeriodLogged ||
                $cycleDay <= $phases['menstrual_end']
            ),

            'is_fertile_window' => (
                $cycleDay >= $phases['fertile_start'] &&
                $cycleDay <= $phases['fertile_end']
            ),

            'is_luteal' => (
                $cycleDay >= $phases['luteal_start'] &&
                $cycleDay <= $phases['luteal_end']
            ),

            'is_ovulation' => (
                $cycleDay >= $phases['ovulatory_start'] &&
                $cycleDay <= $phases['ovulatory_end']
            ),

            /*
            |--------------------------------------------------------------------------
            | Currently Estimated Only
            |--------------------------------------------------------------------------
            */
            'is_confirmed_ovulation' => false,
        ];

        /*
        |--------------------------------------------------------------------------
        | Return Phase Information
        |--------------------------------------------------------------------------
        */
        return [

            'phase' => [
                'name' => $phaseName,
                'key' => $phaseKey,
                'color' => $color,
                'icon' => $icon,
            ],

            'calendar_status' => $calendarStatus,

            'phases' => $phases,
        ];
    }

    /**
     * Calculate Fertile Window Prediction block for UI cards.
     *
     * Window Opens: Day 11 (start_date + 10 days)
     * Peak Day: Day 14 (start_date + 13 days)
     * Window Closes: Day 17 (start_date + 16 days)
     * Span: 7 days
     */
    public static function calculateFertilePrediction(
        Carbon|string $startDate,
        ?int $cycleLength = 28,
        int $fertileStartDay = 11,
        int $fertileEndDay = 17,
        int $peakDay = 14
    ): array {
        $start = $startDate instanceof Carbon ? $startDate->copy()->startOfDay() : Carbon::parse($startDate)->startOfDay();
        $cycleLength = $cycleLength ?: 28;

        $windowOpensDate  = $start->copy()->addDays($fertileStartDay - 1);
        $peakDate         = $start->copy()->addDays($peakDay - 1);
        $windowClosesDate = $start->copy()->addDays($fertileEndDay - 1);
        $spanDays         = max(1, ($fertileEndDay - $fertileStartDay + 1));

        return [
            'title' => 'FERTILE WINDOW PREDICTION',
            'heading' => 'FERTILE WINDOW PREDICTION',
            'start_date' => $start->toDateString(),
            'cycle_length' => $cycleLength,
            'span_days' => $spanDays,
            'description' => "Range-based prediction model — avoids single-day assumptions. Your window spans {$spanDays} days for maximum accuracy.",

            // Flat keys for direct access / backward compatibility
            'window_opens_day' => $fertileStartDay,
            'window_opens_label' => "Day {$fertileStartDay}",
            'window_opens_date' => $windowOpensDate->format('M d'),
            'window_opens_formatted' => $windowOpensDate->format('M j'),
            'window_opens_full' => $windowOpensDate->toDateString(),

            'peak_day' => $peakDay,
            'peak_day_label' => "Day {$peakDay}",
            'peak_date' => $peakDate->format('M d'),
            'peak_date_formatted' => $peakDate->format('M j'),
            'peak_date_full' => $peakDate->toDateString(),

            'window_closes_day' => $fertileEndDay,
            'window_closes_label' => "Day {$fertileEndDay}",
            'window_closes_date' => $windowClosesDate->format('M d'),
            'window_closes_formatted' => $windowClosesDate->format('M j'),
            'window_closes_full' => $windowClosesDate->toDateString(),

            // Card objects matching UI cards
            'window_opens' => [
                'key' => 'window_opens',
                'title' => 'Window Opens',
                'day' => "Day {$fertileStartDay}",
                'day_number' => $fertileStartDay,
                'date' => $windowOpensDate->format('M d'),
                'date_formatted' => $windowOpensDate->format('M j'),
                'full_date' => $windowOpensDate->toDateString(),
                'is_peak' => false,
            ],
            'peak_day_info' => [
                'key' => 'peak_day',
                'title' => 'Peak Day',
                'day' => "Day {$peakDay}",
                'day_number' => $peakDay,
                'date' => $peakDate->format('M d'),
                'date_formatted' => $peakDate->format('M j'),
                'full_date' => $peakDate->toDateString(),
                'is_peak' => true,
            ],
            'window_closes' => [
                'key' => 'window_closes',
                'title' => 'Window Closes',
                'day' => "Day {$fertileEndDay}",
                'day_number' => $fertileEndDay,
                'date' => $windowClosesDate->format('M d'),
                'date_formatted' => $windowClosesDate->format('M j'),
                'full_date' => $windowClosesDate->toDateString(),
                'is_peak' => false,
            ],

            // Cards array for list rendering
            'cards' => [
                [
                    'key' => 'window_opens',
                    'title' => 'Window Opens',
                    'day' => "Day {$fertileStartDay}",
                    'day_number' => $fertileStartDay,
                    'date' => $windowOpensDate->format('M d'),
                    'date_formatted' => $windowOpensDate->format('M j'),
                    'full_date' => $windowOpensDate->toDateString(),
                    'is_peak' => false,
                ],
                [
                    'key' => 'peak_day',
                    'title' => 'Peak Day',
                    'day' => "Day {$peakDay}",
                    'day_number' => $peakDay,
                    'date' => $peakDate->format('M d'),
                    'date_formatted' => $peakDate->format('M j'),
                    'full_date' => $peakDate->toDateString(),
                    'is_peak' => true,
                ],
                [
                    'key' => 'window_closes',
                    'title' => 'Window Closes',
                    'day' => "Day {$fertileEndDay}",
                    'day_number' => $fertileEndDay,
                    'date' => $windowClosesDate->format('M d'),
                    'date_formatted' => $windowClosesDate->format('M j'),
                    'full_date' => $windowClosesDate->toDateString(),
                    'is_peak' => false,
                ],
            ],
        ];
    }
}

