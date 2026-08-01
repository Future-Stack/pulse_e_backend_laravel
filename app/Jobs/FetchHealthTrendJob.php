<?php

namespace App\Jobs;

use App\Models\HealthTrend;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchHealthTrendJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    protected int $healthTrendId;

    protected string $period;

    public function __construct(
        int $healthTrendId,
        string $period = '30d'
    ) {
        $this->healthTrendId = $healthTrendId;
        $this->period = $period;
    }

    public function handle(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Find Health Trend
        |--------------------------------------------------------------------------
        */

        $healthTrend = HealthTrend::find($this->healthTrendId);

        if (! $healthTrend) {

            Log::error('Health Trend record not found.', [
                'health_trend_id' => $this->healthTrendId,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Get User ID
        |--------------------------------------------------------------------------
        */

        $userId = $healthTrend->user_id;

        try {

            /*
            |--------------------------------------------------------------------------
            | pending -> processing
            |--------------------------------------------------------------------------
            */

            $healthTrend->update([
                'status' => 'processing',
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API URL
            |--------------------------------------------------------------------------
            */

            $url = config('services.ai.base_url')
                . '/api/health-trends';

            /*
            |--------------------------------------------------------------------------
            | Call Health Trend AI API
            |--------------------------------------------------------------------------
            |
            | user_id is required by the AI API.
            |
            */

            Log::info('Calling Health Trend AI API', [
                'url' => $url,
                'user_id' => $userId,
                'period' => $this->period,
                'health_trend_id' => $healthTrend->id,
            ]);

            $response = Http::retry(3, 2000)
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(300)
                ->get($url, [

                    'user_id' => $userId,

                    'period' => $this->period,

                ]);

            /*
            |--------------------------------------------------------------------------
            | Log AI Response
            |--------------------------------------------------------------------------
            */

            Log::info('Health Trend AI Response', [
                'status' => $response->status(),
                'user_id' => $userId,
                'body' => $response->body(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Validate AI Response
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {

                throw new \Exception(
                    "AI API Error: {$response->status()} {$response->body()}"
                );
            }

            $data = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Validate Health Trends Data
            |--------------------------------------------------------------------------
            */

            if (! isset($data['health_trends'])) {

                throw new \Exception(
                    'health_trends key not found in AI response.'
                );
            }

            $trend = $data['health_trends'];

            /*
            |--------------------------------------------------------------------------
            | Save AI Response
            |--------------------------------------------------------------------------
            */

            $healthTrend->update([

                'title' => $trend['title'] ?? null,

                'period' => $this->period,

                'range_options' => collect(
                    $trend['range_options'] ?? []
                )
                    ->map(function ($item) {

                        return [
                            'label' => $item['label'] ?? null,

                            'selected' =>
                                ($item['label'] ?? null)
                                === $this->period,
                        ];

                    })
                    ->values()
                    ->toArray(),

                'sleep_energy_correlation_chart' =>
                    $trend['sleep_energy_correlation_chart'] ?? [],

                'sleep_energy_correlation_diagram' =>
                    $trend['sleep_energy_correlation_diagram'] ?? [],

                'hormone_mood' =>
                    $trend['hormone_mood'] ?? [],

                'status' => 'completed',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Success Log
            |--------------------------------------------------------------------------
            */

            Log::info('Health Trend generated successfully.', [
                'health_trend_id' => $healthTrend->id,
                'user_id' => $userId,
                'period' => $this->period,
            ]);

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Failed
            |--------------------------------------------------------------------------
            */

            Log::error('Health Trend Job Failed.', [

                'health_trend_id' => $this->healthTrendId,

                'user_id' => $userId,

                'period' => $this->period,

                'message' => $e->getMessage(),

                'file' => $e->getFile(),

                'line' => $e->getLine(),

            ]);

            $healthTrend->update([
                'status' => 'failed',
            ]);

            throw $e;
        }
    }
}

