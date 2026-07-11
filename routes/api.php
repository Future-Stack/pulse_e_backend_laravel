<?php

use App\Http\Controllers\Booking\InspectionBookingRequestCotroller;
use App\Http\Controllers\Booking\RescheduleBookingRequestController;
use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\Inspection\AdminInspectionController;
use App\Http\Controllers\Inspection\InspectionController;
use App\Http\Controllers\Inspection_Assign\InspectionAssignsController;
use App\Http\Controllers\InspectionBookingController;
use App\Http\Controllers\Inspecttion_Decline\InspectionDeclinesController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationPreferenceController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Payment\AdminPaymentController;
use App\Http\Controllers\Reviews\HomeownerReviewsController;
use App\Http\Controllers\Reviews\InspectorReviewsController;
use App\Http\Controllers\Reviews\ReviewsController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Support\SupportRequestController;
use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\User\DeleteUsersController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InspectionTypeController;
use App\Http\Controllers\User\GoogleAuthController;
use App\Http\Controllers\InspectionReportController;
use App\Http\Controllers\Admin\AdminInspectionReportController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\InspectorManagementController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\InspectorPaymentHistoryController;


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
    Route::post('google/token', [GoogleAuthController::class, 'tokenLogin']);


    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('/user-profile', [AuthController::class, 'getProfile']);

        Route::post('/profile/update', [AuthController::class, 'updateProfile']);


        //Pages
        Route::apiResource('pages', PageController::class)->names('pages.');

        //Settings
        Route::get('settings', [SettingsController::class, 'show']);
        Route::post('settings', [SettingsController::class, 'createOrUpdate']);


        //Notification Preference
        Route::post('/notification-preference-save', [NotificationPreferenceController::class, 'notificationPreference']);
        Route::get('/notification-preference-get', [NotificationPreferenceController::class, 'notificationPreferenceGet']);

        //Fetch All Notifications Record (Admin)
        Route::get('/all-notifications', [NotificationController::class, 'fetchAllNotification']);
        Route::get('/admins-notifications', [NotificationController::class, 'fetchAdminNotification']);

    });

});
