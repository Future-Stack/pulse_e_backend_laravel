<?php

namespace App\Jobs;

use App\Models\SmartAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchSmartAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    protected int $smartAnalysisId;

    public function __construct(int $smartAnalysisId)
    {
        $this->smartAnalysisId = $smartAnalysisId;
    }

    public function handle(): void
    {
        $smartAnalysis = SmartAnalysis::find($this->smartAnalysisId);

        if (! $smartAnalysis) {
            Log::error('SmartAnalysis not found.', [
                'smart_analysis_id' => $this->smartAnalysisId,
            ]);

            return;
        }

        try {

            $smartAnalysis->update([
                'status' => 'processing',
            ]);

            
            $url = config('services.ai.base_url') . '/api/smart-analysis';

            Log::info('Calling Smart Analysis AI API.', [
                'url' => $url,
                'user_id' => $smartAnalysis->user_id,
                'smart_analysis_id' => $smartAnalysis->id,
            ]);

            Log::info('HTTP Method Used', [
                'method' => 'GET',
            ]);

            $response = Http::withoutVerifying()
                ->acceptJson()
                ->timeout(300)
                ->get($url);

            Log::info('Smart Analysis AI API Response.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (! $response->successful()) {
                throw new \Exception(
                    "AI API Error ({$response->status()}): {$response->body()}"
                );
            }

            $data = $response->json();

            if (! isset($data['smart_analysis'])) {
                throw new \Exception(
                    'Invalid API response. smart_analysis key not found.'
                );
            }

            $analysis = $data['smart_analysis'];

            $smartAnalysis->update([
                'title'   => $analysis['title'] ?? null,
                'alerts'  => $analysis['alerts'] ?? [],
                'status'  => 'completed',
            ]);

            Log::info('Smart Analysis generated successfully.', [
                'smart_analysis_id' => $smartAnalysis->id,
            ]);

        } catch (\Throwable $e) {

            Log::error('Smart Analysis generation failed.', [
                'smart_analysis_id' => $this->smartAnalysisId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $smartAnalysis->update([
                'status' => 'failed',
            ]);

            throw $e;
        }
    }
}