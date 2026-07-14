<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function createOrUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'platform_name' => 'required|string|max:255',
                'email'         => 'required|email',
                'logo'          => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            ]);

            // Handle logo upload
            if ($request->hasFile('logo')) {
                $storedPath = $request->file('logo')->store('settings', 'public');
                $validated['logo'] = asset('storage/' . $storedPath);
            }

            // updateOrCreate ensures either update existing row or create new one
            $settings = Setting::updateOrCreate(
                ['id' => 1], // singleton row
                $validated
            );

            return response()->json([
                'success' => true,
                'data'    => $settings,
                'message' => 'Settings saved successfully.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Settings create/update failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Get current settings.
     */
    public function show()
    {
        try {
            $settings = Setting::first();

            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'No settings found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $settings
            ], 200);

        } catch (\Exception $e) {
            Log::error('Setting fetch failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings'
            ], 500);
        }
    }
}
