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



    public function __construct(int $healthTrendId)
    {
        $this->healthTrendId = $healthTrendId;
    }



    public function handle(): void
    {
        $healthTrend = HealthTrend::find($this->healthTrendId);


        if (!$healthTrend) {

            Log::error('Health Trend record not found.', [
                'id' => $this->healthTrendId,
            ]);

            return;
        }



        try {


            // Pending -> Processing
            $healthTrend->update([
                'status' => 'processing',
            ]);



            $url = config('services.ai.base_url')
                . '/api/health-trends';



            Log::info('Calling Health Trend AI API', [
                'url' => $url,
                'health_trend_id' => $healthTrend->id,
                'user_id' => $healthTrend->user_id,
            ]);



            $response = Http::retry(3, 2000)
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(300)
                ->get($url);



            Log::info('Health Trend AI Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);



            if (!$response->successful()) {

                throw new \Exception(
                    "AI API Error: {$response->status()} {$response->body()}"
                );
            }



            $data = $response->json();



            if (!isset($data['health_trends'])) {

                throw new \Exception(
                    'health_trends key not found.'
                );
            }



            $trend = $data['health_trends'];



            $healthTrend->update([

                'title' => $trend['title'] ?? null,


                'range_options' =>
                    $trend['range_options'] ?? [],


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