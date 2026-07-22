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
        $healthTrend = HealthTrend::find($this->healthTrendId);


        if (! $healthTrend) {

            Log::error('Health Trend record not found.', [
                'health_trend_id' => $this->healthTrendId,
            ]);

            return;
        }



        try {

            // pending -> processing
            $healthTrend->update([
                'status' => 'processing',
            ]);



            $url = config('services.ai.base_url')
                . '/api/health-trends';



            Log::info('Calling Health Trend AI API', [
                'url' => $url,
                'period' => $this->period,
                'health_trend_id' => $healthTrend->id,
                'user_id' => $healthTrend->user_id,
            ]);



            $response = Http::retry(3, 2000)
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(300)
                ->get($url, [
                    'period' => $this->period,
                ]);



            Log::info('Health Trend AI Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);



            if (! $response->successful()) {

                throw new \Exception(
                    "AI API Error: {$response->status()} {$response->body()}"
                );
            }



            $data = $response->json();



            if (! isset($data['health_trends'])) {

                throw new \Exception(
                    'health_trends key not found in AI response.'
                );
            }



            $trend = $data['health_trends'];



            $healthTrend->update([

                'title' => $trend['title'] ?? null,

                'period' => $this->period,

                'range_options' => collect($trend['range_options'] ?? [])
                    ->map(function ($item) {

                        return [
                            'label' => $item['label'],
                            'selected' => $item['label'] === $this->period,
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



            Log::info('Health Trend generated successfully.', [
                'health_trend_id' => $healthTrend->id,
            ]);



        } catch (\Throwable $e) {


            Log::error('Health Trend Job Failed.', [

                'health_trend_id' => $this->healthTrendId,

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