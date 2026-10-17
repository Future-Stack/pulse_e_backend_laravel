<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TerraWebhookController extends Controller
{
    public function generateWidgetSession(Request $request)
    {
        $response = Http::withHeaders([
            'dev-id' => config('services.terra.dev_id'),
            'x-api-key' => config('services.terra.api_key'),
        ])->post(config('services.terra.base_url').'/auth/generateWidgetSession', [
            'reference_id' => (string) $request->user()->id,
            'providers' => 'OURA,FITBIT,GARMIN,GOOGLE',
            'language' => 'en',
            'auth_success_redirect_url' => 'https://yourapp.com/terra/success',
            'auth_failure_redirect_url' => 'https://yourapp.com/terra/failure',
        ]);

        return $response->json();
    }

    public function handle(Request $request)
    {
        Log::info('Terra webhook hit', $request->all());

        $signature = $request->header('terra-signature');

        // if (!$this->verifySignature($request->getContent(), $signature)) {
        //     Log::warning('Terra signature verification failed');
        //     return response()->json(['error' => 'invalid signature'], 401);
        // }

        $payload = $request->all();
        $type = $payload['type'] ?? null;
        $terraUserId = $payload['user']['user_id'] ?? null;
        $referenceId = $payload['user']['reference_id'] ?? null;

        $user = $referenceId ? \App\Models\User::find($referenceId) : null;
        if (!$user && $terraUserId) {
            $connection = \App\Models\TerraConnection::where('terra_user_id', $terraUserId)->first();
            $user = $connection?->user;
        }

        try {
            if ($type === 'auth') {
                \App\Models\TerraConnection::updateOrCreate(
                    ['terra_user_id' => $terraUserId],
                    [
                        'user_id' => $user?->id,
                        'reference_id' => $referenceId,
                        'provider' => $payload['user']['provider'] ?? null,
                        'active' => true,
                    ]
                );
            } else {
                $rawStartTime = $payload['data'][0]['metadata']['start_time'] ?? null;
                $generatedAt = $rawStartTime ? \Carbon\Carbon::parse($rawStartTime) : now();
                $targetDate = $generatedAt->toDateString();

                $existingActivity = \App\Models\TerraActivityData::where('user_id', $user?->id)
                    ->where('type', $type)
                    ->whereDate('data_generated_at', $targetDate)
                    ->first();

                if ($existingActivity) {
                    $existingActivity->update([
                        'terra_user_id'     => $terraUserId,
                        'payload'           => $payload,
                        'data_generated_at' => $generatedAt,
                    ]);
                } else {
                    \App\Models\TerraActivityData::create([
                        'user_id'           => $user?->id,
                        'terra_user_id'     => $terraUserId,
                        'type'              => $type,
                        'payload'           => $payload,
                        'data_generated_at' => $generatedAt,
                    ]);
                }

                // Automatically analyze biometrics and sync vasomotor episodes for perimenopause
                if ($user?->id) {
                    try {
                        app(\App\Services\VasomotorWearableSyncService::class)->syncDate($user->id, $targetDate);
                    } catch (\Throwable $syncEx) {
                        Log::warning('Vasomotor wearable sync warning: ' . $syncEx->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Terra webhook save failed: '.$e->getMessage());
        }

        return response()->json(['status' => 'received']);
    }

    private function verifySignature($body, $signature)
    {
        if (!$signature) {
            return false;
        }

        $secret = config('services.terra.signing_secret');
        [$t, $sig] = $this->parseSignatureHeader($signature);
        $expected = hash_hmac('sha256', $t.'.'.$body, $secret);
        return hash_equals($expected, $sig);
    }

    private function parseSignatureHeader($signature)
    {
        $parts = explode(',', $signature);
        $t = null;
        $sig = null;

        foreach ($parts as $part) {
            [$key, $value] = explode('=', $part, 2);
            if ($key === 't') {
                $t = $value;
            } elseif ($key === 'v1') {
                $sig = $value;
            }
        }

        return [$t, $sig];
    }

    public function getActivityData(Request $request)
    {
        $query = \App\Models\TerraActivityData::where('user_id', $request->user()->id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $data = $query->latest()->paginate(20);

        return response()->json($data);
    }

    public function getConnections(Request $request)
    {
        $connections = \App\Models\TerraConnection::where('user_id', $request->user()->id)
            ->get();

        return response()->json($connections);
    }

    public function getScores(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:sleep,energy,hrv,stress,readiness,calories,step,hydration,hydration_ml',
            'date' => 'nullable|date',
        ]);

        $type = $request->type;
        $date = $request->date;

        $typesToFetch = $type ? [$type] : ['sleep', 'energy', 'hrv', 'stress', 'readiness', 'calories', 'step', 'hydration_ml'];

        $terraTypesNeeded = collect($typesToFetch)
            ->map(fn($t) => in_array($t, ['sleep', 'hrv', 'readiness', 'energy']) ? 'sleep' : 'daily')
            ->unique()
            ->values()
            ->toArray();

        $query = \App\Models\TerraActivityData::where('user_id', $request->user()->id)
            ->whereIn('type', $terraTypesNeeded);

        if ($date) {
            $query->whereDate('data_generated_at', $date);
        }

        $records = $query->latest()->get();

        $result = [];

        foreach ($records as $item) {
            $entryDate = $item->data_generated_at ?? $item->created_at;
            $last_update = $item->updated_at;
            foreach ($typesToFetch as $t) {
                $terraType = in_array($t, ['sleep', 'hrv', 'readiness', 'energy']) ? 'sleep' : 'daily';

                if ($item->type !== $terraType) {
                    continue;
                }

                $value = $this->extractScore($item->payload, $t);

                if ($value !== null) {
                    $result[] = [
                        'date' => $entryDate,
                        'last_update' => $last_update,
                        'type' => $t,
                        'value' => $value,
                    ];
                }
            }
        }

        return response()->json([
            'type_filter' => $type ?? 'all',
            'date_filter' => $date ?? 'all',
            'data' => $result,
        ]);
    }

    public function getTodayScores(Request $request)
    {
        $today = now()->timezone(config('app.timezone'))->toDateString(); // যেমন: 2026-07-16

        $types = ['sleep', 'energy', 'hrv', 'stress', 'readiness', 'calories', 'step', 'hydration_ml'];
        $terraTypesNeeded = ['sleep', 'daily', 'body'];

        $userId = $request->user()?->id ?? auth('sanctum')->id();

        $records = \App\Models\TerraActivityData::where('user_id', $userId)
            ->whereIn('type', $terraTypesNeeded)
            ->where(function ($q) use ($today) {
                $q->whereDate('data_generated_at', $today)
                  ->orWhere(function ($sub) use ($today) {
                      $sub->whereNull('data_generated_at')
                          ->whereDate('created_at', $today);
                  });
            })
            ->select('id', 'user_id', 'type', 'payload', 'data_generated_at', 'created_at', 'updated_at')
            ->orderByDesc('updated_at')
            ->get();

        // If no records found for today, check latest available recent records
        if ($records->isEmpty()) {
            $records = \App\Models\TerraActivityData::where('user_id', $userId)
                ->whereIn('type', $terraTypesNeeded)
                ->select('id', 'user_id', 'type', 'payload', 'data_generated_at', 'created_at', 'updated_at')
                ->orderByDesc('updated_at')
                ->take(10)
                ->get();
        }

        $result = [];

        foreach ($types as $t) {
            $value = null;

            // Search through records (daily, sleep, body) for non-null value
            foreach ($records as $record) {
                $val = $this->extractScore($record->payload, $t);
                if ($val !== null) {
                    $value = $val;
                    break;
                }
            }

            $result[$t] = $value;
        }

        return response()->json([
            'date' => $today,
            'data' => $result,
        ]);
    }

    public function syncDeviceData(Request $request)
    {
        Log::info('Health data sync request received', [
            'payload' => $request->all(),
            'headers' => [
                'authorization' => $request->header('Authorization') ? 'Bearer ***' : 'none',
                'user_agent'    => $request->header('User-Agent'),
            ],
        ]);

        try {
            $user = $request->user('sanctum')
                ?? auth('sanctum')->user()
                ?? $request->user();

            if (!$user && ($request->filled('user_id') || $request->filled('userId'))) {
                $targetId = $request->input('user_id') ?? $request->input('userId');
                $user = \App\Models\User::find($targetId);
            }

            if (!$user && ($request->filled('profile_id') || $request->filled('profileId'))) {
                $profileId = $request->input('profile_id') ?? $request->input('profileId');
                $profile = \App\Models\Profile::find($profileId);
                $user = $profile?->user;
            }

            // Fallback for development if no auth provided
            if (!$user && (app()->environment('local') || config('app.debug'))) {
                $user = \App\Models\User::first();
            }

            if (!$user) {
                Log::warning('Health data sync failed: User not authenticated or found', $request->all());
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or not found. Please provide Authorization Bearer token or user_id.',
                ], 401);
            }

            $source = $request->source ?? ($request->is('*apple*') ? 'Apple HealthKit' : ($request->is('*google*') || $request->is('*android*') ? 'Android Health Connect' : 'Health Connect'));
            $lowerSource = strtolower($source);
            if (str_contains($lowerSource, 'google') || str_contains($lowerSource, 'android')) {
                $provider = 'GOOGLE';
            } elseif (str_contains($lowerSource, 'apple') || str_contains($lowerSource, 'ios')) {
                $provider = 'APPLE';
            } else {
                $provider = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $source)) ?: 'HEALTH';
            }
            $terraUserId = strtolower($provider) . '_' . $user->id;

            $steps = $request->has('steps') && $request->steps !== null ? (int) $request->steps : null;
            $heartRate = $request->has('heart_rate') && $request->heart_rate !== null ? (float) $request->heart_rate : null;
            $sleepHours = $request->has('sleep_hours') && $request->sleep_hours !== null ? (float) $request->sleep_hours : null;
            $hydrationMl = $request->has('hydration_ml') && $request->hydration_ml !== null ? (float) $request->hydration_ml : null;
            $temperatureDelta = $request->has('temperature_delta') ? (float) $request->temperature_delta : null;
            $skinTemperature = $request->has('skin_temperature') ? (float) $request->skin_temperature : null;

            $dataGeneratedAt = now();
            if ($request->filled('synced_at')) {
                try {
                    $rawDate = $request->input('synced_at');
                    if (is_numeric($rawDate)) {
                        $sec = strlen((string) $rawDate) > 11 ? (int) ($rawDate / 1000) : (int) $rawDate;
                        $dataGeneratedAt = \Carbon\Carbon::createFromTimestamp($sec);
                    } else {
                        $dataGeneratedAt = \Carbon\Carbon::parse($rawDate);
                    }
                } catch (\Throwable $dateEx) {
                    $dataGeneratedAt = now();
                }
            }

        // 1. Create or update TerraConnection for this user and provider
        \App\Models\TerraConnection::updateOrCreate(
            [
                'user_id'  => $user->id,
                'provider' => $provider,
            ],
            [
                'terra_user_id' => $terraUserId,
                'reference_id'  => (string) $user->id,
                'active'        => true,
            ]
        );

        $targetDate = $dataGeneratedAt->toDateString();

        $existingDaily = \App\Models\TerraActivityData::where('user_id', $user->id)
            ->where('type', 'daily')
            ->whereDate('data_generated_at', $targetDate)
            ->first();

        if ($existingDaily && is_array($existingDaily->payload)) {
            $prev = $existingDaily->payload;
            $steps = $steps ?? ($prev['steps'] ?? null);
            $heartRate = $heartRate ?? ($prev['heart_rate'] ?? null);
            $sleepHours = $sleepHours ?? ($prev['sleep']['hours'] ?? null);
            $hydrationMl = $hydrationMl ?? ($prev['hydration_ml'] ?? null);
        }

        // 2. Save Daily Activity Data (steps, heart rate, hydration, etc.)
        $dailyPayload = [
            'source'       => $source,
            'steps'        => $steps,
            'heart_rate'   => $heartRate,
            'hydration_ml' => $hydrationMl,
            'sleep'        => [
                'hours'   => $sleepHours,
                'quality' => 'Good',
            ],
            'hrv'          => [
                'value'  => $heartRate,
                'status' => 'Normal',
            ],
            'stress'       => [
                'level'  => 20,
                'status' => 'Low',
            ],
            'hydration'    => [
                'amount_ml' => $hydrationMl,
            ],
            'data'         => [
                [
                    'metadata'        => [
                        'start_time' => $dataGeneratedAt->toIso8601String(),
                    ],
                    'distance_data'   => [
                        'steps' => $steps,
                    ],
                    'heart_rate_data' => [
                        'summary' => [
                            'avg_hrv_rmssd' => $heartRate,
                            'avg_hr_bpm'    => $heartRate,
                        ],
                    ],
                    'scores'          => [
                        'sleep' => $sleepHours,
                    ],
                    'hydration_data'  => [
                        'hydration_ml' => $hydrationMl,
                    ],
                    'temperature_data' => [
                        'temperature_delta' => $temperatureDelta,
                        'skin_temperature'  => $skinTemperature,
                    ],
                ],
            ],
        ];

        if ($existingDaily) {
            $existingDaily->update([
                'terra_user_id'     => $terraUserId,
                'payload'           => $dailyPayload,
                'data_generated_at' => $dataGeneratedAt,
            ]);
        } else {
            \App\Models\TerraActivityData::create([
                'user_id'           => $user->id,
                'terra_user_id'     => $terraUserId,
                'type'              => 'daily',
                'payload'           => $dailyPayload,
                'data_generated_at' => $dataGeneratedAt,
            ]);
        }

        // 3. If sleep hours are provided, also update or create a 'sleep' record
        if ($sleepHours !== null) {
            $sleepPayload = [
                'source'     => $source,
                'sleep'      => [
                    'hours'   => $sleepHours,
                    'quality' => 'Good',
                ],
                'hrv'        => [
                    'value'  => $heartRate,
                    'status' => 'Normal',
                ],
                'data'       => [
                    [
                        'metadata'        => [
                            'start_time' => $dataGeneratedAt->toIso8601String(),
                        ],
                        'scores'          => [
                            'sleep' => $sleepHours,
                        ],
                        'heart_rate_data' => [
                            'summary' => [
                                'avg_hrv_rmssd' => $heartRate,
                            ],
                        ],
                        'readiness_data'  => [
                            'readiness'      => 85,
                            'recovery_level' => 'Good',
                        ],
                        'temperature_data' => [
                            'temperature_delta' => $temperatureDelta,
                            'skin_temperature'  => $skinTemperature,
                        ],
                    ],
                ],
            ];

            $existingSleep = \App\Models\TerraActivityData::where('user_id', $user->id)
                ->where('type', 'sleep')
                ->whereDate('data_generated_at', $targetDate)
                ->first();

            if ($existingSleep) {
                $existingSleep->update([
                    'terra_user_id'     => $terraUserId,
                    'payload'           => $sleepPayload,
                    'data_generated_at' => $dataGeneratedAt,
                ]);
            } else {
                \App\Models\TerraActivityData::create([
                    'user_id'           => $user->id,
                    'terra_user_id'     => $terraUserId,
                    'type'              => 'sleep',
                    'payload'           => $sleepPayload,
                    'data_generated_at' => $dataGeneratedAt,
                ]);
            }
        }

        // Automatically analyze biometrics and sync vasomotor episodes for perimenopause
        try {
            app(\App\Services\VasomotorWearableSyncService::class)->syncDate($user->id, $targetDate);
        } catch (\Throwable $syncEx) {
            Log::warning('Vasomotor wearable sync warning in syncDeviceData: ' . $syncEx->getMessage());
        }

            return response()->json([
                'success' => true,
                'message' => 'Health data synced successfully.',
                'synced'  => [
                    'source'       => $source,
                    'steps'        => $steps,
                    'heart_rate'   => $heartRate,
                    'sleep_hours'  => $sleepHours,
                    'hydration_ml' => $hydrationMl,
                    'synced_at'    => $dataGeneratedAt->toIso8601String(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Health data sync unexpected exception: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Health data sync encountered an error: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function extractScore($payload, $type)
    {
        if (!is_array($payload)) {
            return null;
        }

        // Support direct/apple/google health keys at root level
        if ($type === 'step') {
            if (isset($payload['steps']) && $payload['steps'] !== null) return (int) $payload['steps'];
            if (isset($payload['step']) && $payload['step'] !== null) return (int) $payload['step'];
            if (isset($payload['step_count']) && $payload['step_count'] !== null) return (int) $payload['step_count'];
            if (isset($payload['total_steps']) && $payload['total_steps'] !== null) return (int) $payload['total_steps'];
        }

        if ($type === 'sleep') {
            if (isset($payload['sleep']['hours']) && $payload['sleep']['hours'] !== null) return (float) $payload['sleep']['hours'];
            if (isset($payload['sleep_hours']) && $payload['sleep_hours'] !== null) return (float) $payload['sleep_hours'];
            if (isset($payload['sleep']) && is_numeric($payload['sleep'])) return (float) $payload['sleep'];
        }

        if ($type === 'hrv' || $type === 'heart_rate') {
            if (isset($payload['heart_rate']) && $payload['heart_rate'] !== null) return (float) $payload['heart_rate'];
            if (isset($payload['heartRate']) && $payload['heartRate'] !== null) return (float) $payload['heartRate'];
            if (isset($payload['hrv']['value']) && $payload['hrv']['value'] !== null) return (float) $payload['hrv']['value'];
        }

        if ($type === 'hydration' || $type === 'hydration_ml') {
            if (isset($payload['hydration_ml']) && $payload['hydration_ml'] !== null) return (float) $payload['hydration_ml'];
            if (isset($payload['hydration']['amount_ml']) && $payload['hydration']['amount_ml'] !== null) return (float) $payload['hydration']['amount_ml'];
        }

        if ($type === 'calories') {
            if (isset($payload['calories']) && $payload['calories'] !== null) return (float) $payload['calories'];
            if (isset($payload['active_calories']) && $payload['active_calories'] !== null) return (float) $payload['active_calories'];
            if (isset($payload['burned_calories']) && $payload['burned_calories'] !== null) return (float) $payload['burned_calories'];
        }

        $data = $payload['data'][0] ?? null;
        if (!$data) return null;

        switch ($type) {
            case 'sleep':
                return $data['scores']['sleep']
                    ?? $data['data_enrichment']['sleep_score']
                    ?? $data['sleep_data']['sleep_score']
                    ?? null;

            case 'step':
                return $data['distance_data']['steps']
                    ?? $data['step_data']['steps']
                    ?? null;

            case 'hrv':
                return $data['heart_rate_data']['summary']['avg_hrv_rmssd']
                    ?? $data['heart_data']['heart_rate_data']['summary']['avg_hrv_rmssd']
                    ?? $data['heart_rate_data']['summary']['avg_hr_bpm']
                    ?? $data['heart_data']['heart_rate_data']['summary']['avg_hr_bpm']
                    ?? null;

            case 'readiness':
                return $data['readiness_data']['readiness']
                    ?? $data['data_enrichment']['readiness_score']
                    ?? $data['scores']['recovery']
                    ?? null;

            case 'energy':
                return $data['readiness_data']['recovery_level']
                    ?? $data['scores']['activity']
                    ?? (isset($data['MET_data']['avg_level']) ? round((float) $data['MET_data']['avg_level'], 1) : null);

            case 'stress':
                return $data['stress_data']['avg_stress_level'] ?? null;

            case 'calories':
                if (isset($data['calories_data']['total_burned_calories']) && $data['calories_data']['total_burned_calories'] !== null) {
                    return (float) $data['calories_data']['total_burned_calories'];
                }
                if (isset($data['calories_data']['net_activity_calories']) && $data['calories_data']['net_activity_calories'] !== null) {
                    return (float) ($data['calories_data']['net_activity_calories'] + ($data['calories_data']['BMR_calories'] ?? 0));
                }
                if (!empty($data['calories_data']['calorie_samples']) && is_array($data['calories_data']['calorie_samples'])) {
                    $lastSample = end($data['calories_data']['calorie_samples']);
                    if (isset($lastSample['calories']) && $lastSample['calories'] !== null) {
                        return round((float) $lastSample['calories'], 1);
                    }
                }
                if (isset($data['calories_data']['BMR_calories']) && $data['calories_data']['BMR_calories'] !== null) {
                    return (float) $data['calories_data']['BMR_calories'];
                }
                return null;

            case 'hydration':
            case 'hydration_ml':
                return $data['hydration_data']['day_total_water_consumption_ml']
                    ?? $data['hydration_data']['hydration_ml']
                    ?? $data['hydration_data']['amount_ml']
                    ?? null;

            default:
                return null;
        }
    }

}