<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationSettingsController extends Controller
{
    public function getNotificationSettings()
    {
        try {
            $settings = NotificationSetting::find(1);

            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification settings not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification settings fetched successfully.',
                'data'    => $settings,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Fetch notification settings failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification settings.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function createOrUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'email'          => 'required|boolean',
                'push'           => 'required|boolean',
                'security_alert' => 'required|boolean',
            ]);

            // Assuming only one row exists (singleton settings)
            $settings = NotificationSetting::updateOrCreate(
                ['id' => 1],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification settings saved successfully.',
                'data'    => $settings,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Notification settings create/update failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save notification settings.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
