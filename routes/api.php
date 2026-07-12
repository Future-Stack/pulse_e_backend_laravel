<?php

use App\Http\Controllers\Onboarding\OnboardingController;
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
    Route::post('/auth/google/token', [GoogleAuthController::class, 'tokenLogin']);
    //Apple
Route::post('/apple', [AppleAuthController::class, 'login'])->name('api.auth.apple');


    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('/user-profile', [AuthController::class, 'getProfile']);

        Route::post('/profile/update', [AuthController::class, 'updateProfile']);

        //Profile Onboarding
        Route::get('/life-stages', [OnboardingController::class, 'getLifeStages']);
        Route::get('/health-goals', [OnboardingController::class, 'getHealthGoals']);
        Route::get('/activities', [OnboardingController::class, 'getActivities']);
        Route::get('/life-journeys', [OnboardingController::class, 'getLifeJourneys']);
        Route::get('/connect-devices', [OnboardingController::class, 'getConnectDevices']);
        Route::get('/privacy-policy', [OnboardingController::class, 'getPrivacyPolicy']);

        Route::post('/profile-onboarding', [OnboardingController::class, 'profileOnboarding']);

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
