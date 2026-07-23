<?php

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


    Route::middleware('auth:sanctum')->group(function () {

        //user health log
        Route::apiResource('health-logs', HealthLogController::class);
        Route::get('/health-log/today', [HealthLogController::class, 'today']);


        Route::apiResource('lab-reports', LabReportController::class);

        // Get AI Analysis
        Route::get(
            '/ai-lab-reports/{labReport}',
            [LabReportAIController::class, 'show']
        );


        Route::get('/snapshot/{userId}', [SnapshotController::class, 'snapshot']);

        Route::get('/admin/analytics/export', [UserManagementController::class, 'exportAnalytics']);
        Route::get('/daily-scripture/{userId}', [DailyScriptureController::class, 'show']);
        Route::get('/health-trends/{userId}', [HealthTrendController::class, 'show']);


        Route::get('/smart-analysis/{userId}', [SmartAnalysisController::class, 'show']);
        Route::get(
            '/numera-insight/{userId}',
            [NumeraInsightController::class,'show']
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

        //Life Journey
        Route::get('/life-journeys', [LifeJourneyController::class, 'index']);
        Route::get('/life-journeys/{id}', [LifeJourneyController::class, 'show']);

        //Skin Scan
        Route::get('/skin-scans/history', [SkinScanController::class, 'index']);
        Route::get('/skin-scans/{id}', [SkinScanController::class, 'show']);
        Route::post('/skin-scans/analyze', [SkinScanController::class, 'store']);

        //Chat
        Route::post('/chat/response', [ChatController::class, 'handleResponse']);
    });


    // Stripe webhook endpoint
    Route::post('/subscription-stripe/webhook', [SubscriptionController::class, 'handleStripeWebhook']);
    Route::post('/topup-stripe/webhook', [TopupPaymentController::class, 'handleWebhook']);
    Route::post('/terra/webhook', [TerraWebhookController::class, 'handle']);

});
