<?php

use App\Http\Controllers\AI\BBTController;
use App\Http\Controllers\Blog\BlogCategoryController;
use App\Http\Controllers\Blog\BlogCommentController;
use App\Http\Controllers\Blog\BlogController;
use App\Http\Controllers\Notification\NotificationSettingsController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Subscription\SubscriptionController;
use App\Http\Controllers\Subscription\SubscriptionPlanController;
use App\Http\Controllers\Topup\TopupController;
use App\Http\Controllers\Topup\TopupPaymentController;
use App\Http\Controllers\User\AppleAuthController;
use App\Http\Controllers\User\GoogleAuthController;
use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationPreferenceController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\User\DeleteUsersController;
use App\Http\Controllers\Waitlist\WaitlistController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\CommunityCommentController;
use App\Http\Controllers\CommunityLikeController;
use App\Http\Controllers\CommunityPostController;
use App\Http\Controllers\CommunityPostReportController;
use App\Http\Controllers\TerraWebhookController;
use App\Http\Controllers\User\HealthLogController;
use App\Http\Controllers\LabReportController;
use App\Http\Controllers\AI\LabReportAIController;
use App\Http\Controllers\ChatController\ChatController;
use App\Http\Controllers\Life_journey\LifeJourneyController;
use App\Http\Controllers\SkinScan\SkinScanController;
use App\Http\Controllers\SnapshotController;
use App\Http\Controllers\AI\DailyScriptureController;
use App\Http\Controllers\AI\HealthTrendController;
use App\Http\Controllers\AI\SmartAnalysisController;
use App\Http\Controllers\AI\NumeraInsightController;
use App\Http\Controllers\AI\CycleSummaryController;
use App\Http\Controllers\AI\CalendarController;
use App\Http\Controllers\AI\AvoidingPregnancyController;
use App\Http\Controllers\AI\OpkLogController;
use App\Http\Controllers\AI\TryingToConceiveController;
use App\Http\Controllers\AI\AwarenessController;
use App\Http\Controllers\AI\CycleCalendarInputController;


Route::prefix('v1')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'message' => 'Welcome to API v1',
            'status' => 'ok',
            'version' => '1.0'
        ]);
    });


    // ----------------------------
    // Public Routes
    // ----------------------------
    Route::post('register', [AuthController::class, 'register']);

    Route::post('login', [AuthController::class, 'login']);
    Route::post('resend-otp', [AuthController::class, 'resendOtp']);

    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    // Google OAuth (public, no auth required)
    Route::post('/google/token', [GoogleAuthController::class, 'tokenLogin']);
    //Apple
    Route::post('/apple', [AppleAuthController::class, 'login'])->name('api.auth.apple');

// Public routes (no auth required)
// Public Pages
    Route::get('pages', [PageController::class, 'index']);
    Route::get('pages/{page_id}', [PageController::class, 'show']);

Route::get('lab-reports/{labReport}', [LabReportController::class, 'show']);


Route::get(
    '/cycle-calendar-inputs/{user_id}',
    [CycleCalendarInputController::class, 'show']
);

    Route::middleware('auth:sanctum')->group(function () {

    Route::post(
    '/cycle-calendar-inputs',
    [CycleCalendarInputController::class, 'store']
);

        //user health log
        Route::apiResource('health-logs', HealthLogController::class);
        Route::get('/health-log/today', [HealthLogController::class, 'today']);


        Route::apiResource('lab-reports', LabReportController::class)
        ->except(['show']);

        // Get AI Analysis
        Route::get(
            '/ai-lab-reports/{labReport}',
            [LabReportAIController::class, 'show']
        );

        //Cycle part
//summary new 1st page
  Route::get('/cycle-engine/engine/sync', [CycleSummaryController::class, 'sync']);

        Route::get('/cycle-engine/engine/sync-summary', [CycleSummaryController::class, 'sync']);

        Route::get('/engine/signal-status-sync', [CycleSummaryController::class, 'syncSignalStatus']);
        Route::get('engine/discrepancy-note-sync', [CycleSummaryController::class, 'syncDiscrepancyNote']);

        //calender
        Route::get('/calendar/month-sync', [CalendarController::class, 'syncMonth']);
        Route::post('/calendar/confirm-day', [CalendarController::class, 'confirmDay']);


        Route::get('/calendar/next-period-sync', [CalendarController::class, 'syncNextPeriod']);

        Route::get('/avoiding-pregnancy/consent-status', [AvoidingPregnancyController::class, 'consentStatus']);

        Route::post('/avoiding-pregnancy/consent', [AvoidingPregnancyController::class, 'consent']);

        Route::post('/mode', [AvoidingPregnancyController::class, 'setMode']);
        Route::get('/ttc/surge-banner', [TryingToConceiveController::class, 'surgeBanner']);

        Route::get('/ttc/priority-map', [TryingToConceiveController::class, 'priorityMap']);

        Route::get('/ttc/priority-banner', [TryingToConceiveController::class, 'priorityBanner']);
        Route::get('/awareness/sync', [AwarenessController::class, 'sync']);

        Route::get('/snapshot/{userId}', [SnapshotController::class, 'snapshot']);

        Route::get('/admin/analytics/export', [UserManagementController::class, 'exportAnalytics']);
        Route::get('/daily-scripture/{userId}', [DailyScriptureController::class, 'show']);
        Route::get('/health-trends/{userId}', [HealthTrendController::class, 'show']);


        Route::get('/smart-analysis/{userId}', [SmartAnalysisController::class, 'show']);
        Route::get(
            '/numera-insight/{userId}',
            [NumeraInsightController::class, 'show']
        );

        //Admin Dashboard
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users-details/{id}', [UserManagementController::class, 'show']);
        Route::get('/user/subscription', [UserManagementController::class, 'subscriptions']);
        Route::get('/admin/dashboard', [UserManagementController::class, 'dashboard']);
        Route::get('/analytics', [UserManagementController::class, 'analytic']);
        Route::get('/admin/revenue-breakdown', [SubscriptionController::class, 'revenueBreakdown']);

        Route::post('/change-password', [AuthController::class, 'changePassword']);
        // Save Firebase device token
        Route::post('/save-fcm-token', [AuthController::class, 'saveFcmToken']);

        Route::post('logout', [AuthController::class, 'logout']);
        //Delete User(self)
        Route::post('/delete-user', [DeleteUsersController::class, 'destroy']);
        //suspend user reason
        Route::post('/users/suspend', [AuthController::class, 'suspendUser']);
        //user status
        Route::post('/users/update-status', [AuthController::class, 'updateStatus']);

        Route::get('/user-profile', [ProfileController::class, 'getProfile']);

        Route::post('/profile/update', [ProfileController::class, 'saveProfile']);

        //Profile Onboarding
        Route::get('/life-stages', [OnboardingController::class, 'getLifeStages']);
        Route::get('/health-goals', [OnboardingController::class, 'getHealthGoals']);
        Route::get('/activities', [OnboardingController::class, 'getActivities']);
        Route::get('/life-journeys', [OnboardingController::class, 'getLifeJourneys']);
        Route::get('/connect-devices', [OnboardingController::class, 'getConnectDevices']);
        Route::get('/privacy-policy', [OnboardingController::class, 'getPrivacyPolicy']);

        Route::post('/profile-onboarding', [OnboardingController::class, 'profileOnboarding']);


        // Pages (Admin/Auth)
        Route::post('pages', [PageController::class, 'store']);
        Route::put('pages/{page_id}', [PageController::class, 'update']);
        Route::delete('pages/{page_id}', [PageController::class, 'destroy']);


        //Settings
        Route::get('settings', [SettingsController::class, 'show']);
        Route::post('settings', [SettingsController::class, 'createOrUpdate']);


        //Notification Settings
        Route::post('/notification-settings', [NotificationSettingsController::class, 'createOrUpdate']);
        Route::get('/notification-settings', [NotificationSettingsController::class, 'getNotificationSettings']);

        //Fetch All Notifications Record (Admin)
        Route::get('/admins-notifications', [NotificationController::class, 'fetchAdminNotification']);
        Route::get('/users-notifications', [NotificationController::class, 'fetchUserNotification']);
        Route::get('/admin-read-notifications', [NotificationController::class, 'fetchReadAdminNotification']);

        //Mark single notification as read
        Route::get('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        //Mark all notification as read
        Route::get('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);


        // Community Post

        Route::get('/posts', [CommunityPostController::class, 'index']);
        Route::post('/posts', [CommunityPostController::class, 'store']);
        Route::get('/posts/{post}', [CommunityPostController::class, 'show']);
        Route::put('/posts/{post}', [CommunityPostController::class, 'update']);
        Route::delete('/posts/{post}', [CommunityPostController::class, 'destroy']);

        // Comments (nested under a post)
        Route::get('/posts/{post}/comments', [CommunityCommentController::class, 'index']);
        Route::post('/posts/{post}/comments', [CommunityCommentController::class, 'store']);
        Route::delete('/comments/{comment}', [CommunityCommentController::class, 'destroy']);

        // Likes (toggle)
        Route::post('/posts/{post}/like', [CommunityLikeController::class, 'toggle']);

        // Reports
        Route::post('/posts/{post}/report', [CommunityPostReportController::class, 'store']);

        Route::get('/reports', [CommunityPostReportController::class, 'index']);

        Route::patch('/reports/{report}/approve', [CommunityPostReportController::class, 'approve']);
        Route::patch('/reports/{report}/decline', [CommunityPostReportController::class, 'decline']);

        // Approve Post
        Route::post('/posts/{post}/approve', [CommunityPostController::class, 'approve']);

        // Decline Post
        Route::post('/posts/{post}/decline', [CommunityPostController::class, 'decline']);

        Route::post('/terra/widget-session', [TerraWebhookController::class, 'generateWidgetSession']);
        Route::get('/terra/activity-data', [TerraWebhookController::class, 'getActivityData']);
        Route::get('/terra/connections', [TerraWebhookController::class, 'getConnections']);

        //Subscription Plans
        Route::get('/subscription-plans', [SubscriptionPlanController::class, 'getAllPlans']);
        Route::get('/subscription-plan/{slug}', [SubscriptionPlanController::class, 'getPlanBySlug']);
        Route::post('/update-subscription-plan/{slug}', [SubscriptionPlanController::class, 'createOrUpdate']);

        //TopUp
        Route::get('/topups', [TopupController::class, 'getAll']);
        Route::get('/topup/{slug}', [TopupController::class, 'getBySlug']);
        Route::post('/update-topup/{slug}', [TopupController::class, 'createOrUpdate']);


        //Subscription Payment
        Route::post('/subscriptions', [SubscriptionController::class, 'createSubscription']);
        Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancelSubscription']);
        Route::get('/terra/scores', [TerraWebhookController::class, 'getScores']);
        Route::get('/terra/today-scores', [TerraWebhookController::class, 'getTodayScores']);

        //topup-payment
        Route::post('/topup-payment', [TopupPaymentController::class, 'topUpPayment']);

        //Check Subscription by User
        Route::get('/check-user-subscription', [SubscriptionController::class, 'checkSubscriptionUser']);

        //Life Journey
        Route::get('/life-journeys', [LifeJourneyController::class, 'index']);
        Route::get('/life-journeys/{id}', [LifeJourneyController::class, 'show']);

        //Skin Scan
        Route::get('/skin-scans/history', [SkinScanController::class, 'index']);
        Route::get('/skin-scans/{id}', [SkinScanController::class, 'show']);
        Route::post('/skin-scans/analyze', [SkinScanController::class, 'store']);
        Route::get('/skin-scans/history-date', [SkinScanController::class, 'historyByDate']);

        //Chat
        Route::post('/chat/response', [ChatController::class, 'handleResponse']);
        Route::get('/chat/sessions', [ChatController::class, 'getUserSessions']);
        Route::get('/chat/response/{sessionId}', [ChatController::class, 'getLatestMessages']);



        //Waitlist
        Route::get('/waitlist/list', [WaitlistController::class, 'getWaitlist']);

        // OPK
        Route::get('/get-opk', [OpkLogController::class, 'getOpkUiData']);
        Route::get('/get-opk-data', [OpkLogController::class, 'getStoredOpkData']);
        Route::post('/store-opk-data', [OpkLogController::class, 'storeOpkUiData']);
        Route::get('/opk-history', [OpkLogController::class, 'getOpkDataHistory']);
        Route::get('/cycle-engine/opk/testing-window', [OpkLogController::class, 'testingWindow']);
        Route::post('/cycle-engine/opk/log', [OpkLogController::class, 'store']);
        Route::get('/cycle-engine/opk/today-status', [OpkLogController::class, 'todayStatus']);

        //Blog
        Route::prefix('blog-categories')->group(function () {
          // List all categories
            Route::post('/', [BlogCategoryController::class, 'store']);  // Create new category

            Route::put('/{slug}', [BlogCategoryController::class, 'update']); // Update category
            Route::delete('/{slug}', [BlogCategoryController::class, 'destroy']); // Delete category
        });


        // List all blogs
        Route::post('/blogs', [BlogController::class, 'store']);  // Create new blog
        Route::post('/blog-update/{slug}', [BlogController::class, 'update']); // Update blog
        Route::delete('/blog-delete/{slug}', [BlogController::class, 'destroy']); // Delete blog

        //BBT
        Route::post('/bbt/logs', [BbtController::class, 'logBbtData']);
        Route::get('/bbt-logs', [BbtController::class, 'fetchLog']);
        Route::get('/bbt-database-logs', [BbtController::class, 'fetchBbtLogs']);
    });

    //Fetch-BBT-Logs


    Route::get('/blog-categories', [BlogCategoryController::class, 'index']);
    Route::get('/blog-categories/{slug}', [BlogCategoryController::class, 'show']); // Show single category

    Route::get('/blogs', [BlogController::class, 'index']);
    Route::get('/blog/{slug}', [BlogController::class, 'show']); // Show single blog

    Route::prefix('blogs/{blogId}/comments')->group(function () {
        Route::get('/', [BlogCommentController::class, 'index']);   // List comments for a blog
        Route::post('/', [BlogCommentController::class, 'store']);  // Add new comment
    });

    //Waitlist
    Route::get('/waitlist/list', [WaitlistController::class, 'getWaitlist']);

    Route::post('/waitlist/submit', [WaitlistController::class, 'submit']);
    Route::get('/waitlist/confirmation/{token}', [WaitlistController::class, 'confirmation']);
    Route::post('/waitlist/invite', [WaitlistController::class, 'sendSingleInvite']);
    Route::get('/waitlist/unsubscribe/{token}', [WaitlistController::class, 'unsubscribe']);

    // Stripe webhook endpoint
    Route::post('/subscription-stripe/webhook', [SubscriptionController::class, 'handleStripeWebhook']);
    Route::post('/topup-stripe/webhook', [TopupPaymentController::class, 'handleWebhook']);
    Route::post('/terra/webhook', [TerraWebhookController::class, 'handle']);


});

require __DIR__ . '/marketplace_api.php';
