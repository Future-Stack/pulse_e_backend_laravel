<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\HealthLog;
use App\Models\LabReport;
use App\Models\LifeJourney;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\TerraActivityData;
use Illuminate\Http\Request;



class UserManagementController extends Controller
{
    /**
     * Display a paginated list of users.
     */
    public function index(): JsonResponse
    {
        $users = User::with([
                'profile.lifeJourneys',
                'latestSubscription.subscriptionPlan',
            ])
            ->where('id', '!=', 1) // Exclude Super Admin
            ->latest()
            ->paginate(10);

        $data = $users->getCollection()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'plan' => $user->latestSubscription?->subscriptionPlan?->name,
                'journey' => $user->profile?->lifeJourneys->first()?->title,
                'last_login' => $user->last_login_at?->diffForHumans(),
                'status' => ucfirst($user->status),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'User list retrieved successfully.',
            'data' => $data,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'next_page' => $users->hasMorePages()
                    ? $users->currentPage() + 1
                    : null,
                'prev_page' => $users->currentPage() > 1
                    ? $users->currentPage() - 1
                    : null,
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 200);
    }



    public function show($id): JsonResponse
{
    $user = User::with([
        'profile.lifeStage',
        'profile.activity',
        'profile.healthGoals',
        'profile.lifeJourneys',
        'latestSubscription.subscriptionPlan',
    ])
    ->where('id', '!=', 1)
    ->findOrFail($id);

    return response()->json([
        'success' => true,
        'message' => 'User details retrieved successfully.',
        'data' => [
            'account_information' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'age' => $user->profile?->age,
                'joined' => optional($user->created_at)->format('F d, Y'),
                'status' => ucfirst($user->status),
                'last_login' => $user->last_login_at?->diffForHumans(),
                'subscription_plan' => $user->latestSubscription?->subscriptionPlan?->name,
            ],

            'health_profile' => [
                'life_stage' => $user->profile?->lifeStage?->title,
                'health_goal' => $user->profile?->healthGoals?->pluck('title')->implode(', '),
                'activity' => $user->profile?->activity?->title,
                'life_journey' => $user->profile?->lifeJourneys?->pluck('title')->implode(', '),
                'health_condition' => $user->profile?->bio,
            ],
        ],
    ], 200);
}

/**
 * Display subscription payment list.
 */
/**
 * Display subscription payment list.
 */
public function subscriptions(): JsonResponse
{
    $subscriptions = Payment::with([
            'user.profile',
            'subscriptionPlan',
        ])
        ->where('type', 'subscription')
        ->whereIn('status', ['paid', 'cancel'])
        ->latest()
        ->paginate(10);


    $data = $subscriptions->getCollection()->map(function (Payment $payment) {

        return [

            'id' => $payment->id,

            'user' => $payment->user?->full_name,

            'email' => $payment->user?->email,

            'profile' => $payment->user?->profile?->profile_img,

            'plan' => $payment->subscriptionPlan?->name,

            'amount' => '$' . number_format($payment->amount, 2),

            'date' => $payment->created_at->format('d M Y'),

            'status' => $payment->status === 'paid'
                ? 'Paid'
                : 'Cancel',

        ];

    });


    return response()->json([

        'success' => true,

        'message' => 'Subscription list retrieved successfully.',

        'data' => $data,

        'pagination' => [

            'current_page' => $subscriptions->currentPage(),

            'next_page' => $subscriptions->hasMorePages()
                ? $subscriptions->currentPage() + 1
                : null,

            'prev_page' => $subscriptions->currentPage() > 1
                ? $subscriptions->currentPage() - 1
                : null,

            'last_page' => $subscriptions->lastPage(),

            'per_page' => $subscriptions->perPage(),

            'total' => $subscriptions->total(),

        ],

    ], 200);
}



     /**
     * Dashboard Overview
     */
    public function dashboard(): JsonResponse
    {
        // ==========================
        // Overview Cards
        // ==========================

        $totalUsers = User::where('id', '!=', 1)->count();

       $totalHealthLogs = HealthLog::count();

    $totalTerraRecords = TerraActivityData::count();

    $totalHealthActivities = $totalHealthLogs + $totalTerraRecords;

        $monthlyRevenue = Payment::where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $communityPosts = CommunityPost::count();

        // ==========================
        // Weekly Revenue
        // ==========================

        $weeklyRevenue = [];

        foreach (range(0, 6) as $day) {

            $date = now()->startOfWeek()->addDays($day);

            $weeklyRevenue[] = [
                'day' => $date->format('D'),
                'amount' => (float) Payment::where('status', 'paid')
                    ->whereDate('created_at', $date)
                    ->sum('amount'),
            ];
        }

        // ==========================
        // User Growth (ALL Months)
        // ==========================

        $userGrowth = User::where('id', '!=', 1)
            ->selectRaw("
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                COUNT(*) as users
            ")
            ->groupByRaw("
                YEAR(created_at),
                MONTH(created_at)
            ")
            ->orderByRaw("
                YEAR(created_at),
                MONTH(created_at)
            ")
            ->get()
            ->map(function ($item) {

                return [
                    'month' => Carbon::create(
                        $item->year,
                        $item->month
                    )->format('M Y'),

                    'users' => (int) $item->users,
                ];
            });
                    // ==========================
        // Recent Activity
        // ==========================

        $activities = collect();

        // New Users
        foreach (User::where('id', '!=', 1)
            ->latest()
            ->take(5)
            ->get() as $user) {

            $activities->push([
                'title' => $user->full_name . ' signed up',
                'time' => $user->created_at->diffForHumans(),
                'created_at' => $user->created_at,
            ]);
        }

        // Subscription Purchases
        foreach (
            Payment::with(['user', 'subscriptionPlan'])
                ->where('type', 'subscription')
                ->where('status', 'paid')
                ->latest()
                ->take(5)
                ->get() as $payment
        ) {

            $activities->push([
                'title' => $payment->user?->full_name . ' subscribed to ' . ($payment->subscriptionPlan?->name ?? 'Plan'),
                'time' => $payment->created_at->diffForHumans(),
                'created_at' => $payment->created_at,
            ]);
        }

        // Lab Reports
        foreach (
            LabReport::with('user')
                ->latest()
                ->take(5)
                ->get() as $report
        ) {

            $activities->push([
                'title' => $report->user?->full_name . ' uploaded a lab report',
                'time' => $report->created_at->diffForHumans(),
                'created_at' => $report->created_at,
            ]);
        }

        // Community Posts
        foreach (
            CommunityPost::with('user')
                ->latest()
                ->take(5)
                ->get() as $post
        ) {

            $activities->push([
                'title' => $post->user?->full_name . ' created a community post',
                'time' => $post->created_at->diffForHumans(),
                'created_at' => $post->created_at,
            ]);
        }

        $recentActivities = $activities
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        // ==========================
        // Plan Distribution
        // ==========================

        $planDistribution = SubscriptionPlan::where('status', true)
            ->withCount(['payments as total' => function ($query) {
                $query->where('type', 'subscription')
                    ->where('status', 'paid');
            }])
            ->get()
            ->map(function ($plan) {
                return [
                    'plan'  => $plan->name,
                    'count' => (int) $plan->total,
                ];
            });

        // ==========================
        // Journey Distribution
        // ==========================

        $journeyDistribution = LifeJourney::leftJoin(
                'life_journey_profile',
                'life_journeys.id',
                '=',
                'life_journey_profile.life_journey_id'
            )
            ->select(
                'life_journeys.title',
                DB::raw('COUNT(life_journey_profile.id) as total')
            )
            ->groupBy(
                'life_journeys.id',
                'life_journeys.title'
            )
            ->get();

        $totalJourney = $journeyDistribution->sum('total');

        $journeyDistribution = $journeyDistribution->map(function ($item) use ($totalJourney) {

            return [
                'journey' => $item->title,
                'count' => (int) $item->total,
                'percentage' => $totalJourney > 0
                    ? round(($item->total / $totalJourney) * 100)
                    : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Dashboard overview retrieved successfully.',
            'data' => [

                'overview' => [
                    'total_users' => $totalUsers,
                    'health_logs' => $totalHealthActivities,
                    'monthly_revenue' => $monthlyRevenue,
                    'community_posts' => $communityPosts,
                ],

                'weekly_revenue' => $weeklyRevenue,

                'user_growth' => $userGrowth,

                'recent_activities' => $recentActivities,

                'plan_distribution' => $planDistribution,

                'journey_distribution' => $journeyDistribution,
            ],
        ], 200);
    }





  // Analytics part
            public function analytic(Request $request): JsonResponse
            {
                $range = $request->query('range');

                $fromDate = match ($range) {
                    '7days'  => now()->subDays(7),
                    '30days' => now()->subDays(30),
                    '90days' => now()->subDays(90),
                    '1year'  => now()->subYear(),
                    default  => null,
                };

                /*
                |--------------------------------------------------------------------------
                | Community Activity Growth
                |--------------------------------------------------------------------------
                */
                $communityGrowth = CommunityPost::when($fromDate, function ($query) use ($fromDate) {
                        $query->where('created_at', '>=', $fromDate);
                    })
                    ->selectRaw("
                        YEAR(created_at) as year,
                        MONTH(created_at) as month,
                        COUNT(*) as total
                    ")
                    ->groupByRaw("YEAR(created_at), MONTH(created_at)")
                    ->orderByRaw("YEAR(created_at), MONTH(created_at)")
                    ->get()
                    ->map(function ($item) {
                        return [
                            'month' => Carbon::create($item->year, $item->month)->format('M'),
                            'posts' => (int) $item->total,
                        ];
                    });
                /*
            |--------------------------------------------------------------------------
            | Queries By Journey
            |--------------------------------------------------------------------------
            */
            $queriesByJourney = DB::table('life_journeys')
                ->leftJoin(
                    'community_post_life_journey',
                    'life_journeys.id',
                    '=',
                    'community_post_life_journey.life_journey_id'
                )
                ->when($fromDate, function ($query) use ($fromDate) {
                    $query->where('community_post_life_journey.created_at', '>=', $fromDate);
                })
                ->select(
                    'life_journeys.title',
                    DB::raw('COUNT(community_post_life_journey.community_post_id) as total')
                )
                ->groupBy('life_journeys.title')
                ->orderByDesc('total')
                ->get()
                ->map(function ($item) {
                    return [
                        'journey' => $item->title,
                        'queries' => (int) $item->total,
                    ];
                });

            /*
            |--------------------------------------------------------------------------
            | MRR Growth (Monthly Recurring Revenue)
            |--------------------------------------------------------------------------
            */
           $mrrGrowth = Payment::when($fromDate, function ($query) use ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                })
                ->where('status', 'paid')
                ->selectRaw("
                    YEAR(created_at) as year,
                    MONTH(created_at) as month,
                    SUM(amount) as revenue
                ")
                ->groupByRaw("YEAR(created_at), MONTH(created_at)")
                ->orderByRaw("YEAR(created_at), MONTH(created_at)")
                ->get()
                ->map(function ($item) {
                    return [
                        'month'   => Carbon::create($item->year, $item->month)->format('M'),
                        'revenue' => (float) $item->revenue,
                    ];
                });

                /*
            |--------------------------------------------------------------------------
            | Top Health Concerns Logged (Health Logs + Terra Data)
            |--------------------------------------------------------------------------
            */

            $healthCounts = [];

            /*
            |--------------------------------------------------------------------------
            | Health Logs
            |--------------------------------------------------------------------------
            */

            $logs = HealthLog::when($fromDate, function ($query) use ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                })
                ->whereNotNull('energy_level')
                ->get();

            foreach ($logs as $log) {

                $energy = trim($log->energy_level);

                if (!empty($energy)) {
                    $healthCounts['Fatigue / Low Energy'] =
                        ($healthCounts['Fatigue / Low Energy'] ?? 0) + 1;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Terra Activity Data
            |--------------------------------------------------------------------------
            */

            $terraActivities = TerraActivityData::when($fromDate, function ($query) use ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                })
                ->get();

            foreach ($terraActivities as $activity) {

                $payload = is_array($activity->payload)
                    ? $activity->payload
                    : json_decode($activity->payload, true);

                if (!is_array($payload)) {
                    continue;
                }

                if (isset($payload['sleep'])) {
                    $healthCounts['Sleep Disruption'] =
                        ($healthCounts['Sleep Disruption'] ?? 0) + 1;
                }

                if (isset($payload['hrv'])) {
                    $healthCounts['HRV'] =
                        ($healthCounts['HRV'] ?? 0) + 1;
                }

                if (isset($payload['stress'])) {
                    $healthCounts['Stress'] =
                        ($healthCounts['Stress'] ?? 0) + 1;
                }

                if (isset($payload['readiness'])) {
                    $healthCounts['Readiness'] =
                        ($healthCounts['Readiness'] ?? 0) + 1;
                }

                if (isset($payload['skin'])) {
                    $healthCounts['Skin Redness / Breakout'] =
                        ($healthCounts['Skin Redness / Breakout'] ?? 0) + 1;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Percentage Calculation
            |--------------------------------------------------------------------------
            */

            arsort($healthCounts);

            $highest = !empty($healthCounts)
                ? max($healthCounts)
                : 1;

            $topHealthConcerns = collect($healthCounts)
                ->map(function ($count, $name) use ($highest) {

                    return [
                        'concern'   => $name,
                        'count'     => $count,
                        'percentage'=> round(($count / $highest) * 100),
                    ];

                })
                ->values();
                /*
            |--------------------------------------------------------------------------
            | Life Journey Growth (Monthly)
            |--------------------------------------------------------------------------
            */

            $lifeJourneyGrowth = DB::table('life_journey_profile')
                ->join('profiles', 'profiles.id', '=', 'life_journey_profile.profile_id')
                ->join('life_journeys', 'life_journeys.id', '=', 'life_journey_profile.life_journey_id')
                ->when($fromDate, function ($query) use ($fromDate) {
                    $query->where('life_journey_profile.created_at', '>=', $fromDate);
                })
                ->selectRaw("
                    YEAR(life_journey_profile.created_at) as year,
                    MONTH(life_journey_profile.created_at) as month,
                    life_journeys.title,
                    COUNT(*) as total
                ")
                ->groupByRaw("
                    YEAR(life_journey_profile.created_at),
                    MONTH(life_journey_profile.created_at),
                    life_journeys.title
                ")
                ->orderByRaw("
                    YEAR(life_journey_profile.created_at),
                    MONTH(life_journey_profile.created_at)
                ")
                ->get()
                ->groupBy(function ($item) {
                    return Carbon::create($item->year, $item->month)->format('M');
                })
                ->map(function ($items, $month) {
                    return [
                        'month' => $month,
                        'journeys' => $items->map(function ($item) {
                            return [
                                'journey' => $item->title,
                                'users' => (int) $item->total,
                            ];
                        })->values(),
                    ];
                })
                ->values();

            /*
            |--------------------------------------------------------------------------
            | Life Journey Metrics Breakdown
            |--------------------------------------------------------------------------
            */

            $lifeJourneyBreakdown = LifeJourney::leftJoin(
                    'life_journey_profile',
                    'life_journeys.id',
                    '=',
                    'life_journey_profile.life_journey_id'
                )
                ->when($fromDate, function ($query) use ($fromDate) {
                    $query->where('life_journey_profile.created_at', '>=', $fromDate);
                })
                ->select(
                    'life_journeys.title',
                    DB::raw('COUNT(life_journey_profile.profile_id) as users')
                )
                ->groupBy('life_journeys.id', 'life_journeys.title')
                ->orderByDesc('users')
                ->get()
                ->map(function ($item) {
                    return [
                        'journey' => $item->title,
                        'users' => (int) $item->users,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Analytics retrieved successfully.',
                'data' => [
                    'range' => $range,
                    'community_activity_growth' => $communityGrowth,
                    'queries_by_journey' => $queriesByJourney,
                    'mrr_growth' => $mrrGrowth,
                    'top_health_concerns' => $topHealthConcerns,
                    'life_journey_growth' => $lifeJourneyGrowth,
                    'life_journey_metrics_breakdown' => $lifeJourneyBreakdown,
                ],
            ], 200);
        }




        //export
        public function exportAnalytics()
        {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename=analytics.csv',
            ];

            $callback = function () {
                $file = fopen('php://output', 'w');

                // Header
                fputcsv($file, ['Journey', 'Users']);

                $data = LifeJourney::leftJoin(
                        'life_journey_profile',
                        'life_journeys.id',
                        '=',
                        'life_journey_profile.life_journey_id'
                    )
                    ->select(
                        'life_journeys.title',
                        DB::raw('COUNT(life_journey_profile.profile_id) as users')
                    )
                    ->groupBy('life_journeys.id', 'life_journeys.title')
                    ->get();

                foreach ($data as $row) {
                    fputcsv($file, [
                        $row->title,
                        $row->users,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }
    }   

