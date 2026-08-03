<?php

namespace App\Jobs;

use App\Models\LabReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessLabReportAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;

    public function __construct(
        public int $reportId
    ) {}

    public function handle(): void
    {
        Log::info('ProcessLabReportAI Started', [
            'report_id' => $this->reportId,
        ]);

        $labReport = LabReport::find($this->reportId);

        if (!$labReport) {
            Log::error('Lab Report Not Found', [
                'report_id' => $this->reportId,
            ]);
            return;
        }

        try {
            // Mark as processing
            $labReport->update([
                'analysis_status' => 'processing',
            ]);

            /*
             * AI API expects:
             *
             * GET /api/summarize-pdf?report_id=10
             *
             * report_id must be sent as a query parameter.
             */

            $url = config('services.ai.base_url') . '/api/summarize-pdf';

            Log::info('Calling AI PDF Summary API', [
                'url' => $url,
                'report_id' => $labReport->id,
            ]);

            $response = Http::retry(3, 2000)
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(600)
                ->get($url, [
                    'report_id' => $labReport->id,
                ]);

            Log::info('AI Response', [
                'report_id' => $labReport->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                throw new \Exception(
                    'AI Service Error: '
                    . $response->status()
                    . ' '
                    . $response->body()
                );
            }

            $result = $response->json();
            $summary = $result['summary'] ?? [];

            // Save AI result
            $labReport->update([
                'panel' => $summary['panel'] ?? null,
                'biomarkers' => $summary['biomarkers'] ?? null,
                'ai_insights' => $summary['ai_insights'] ?? null,
                'next_steps' => $summary['next_steps'] ?? null,
                'analysis_status' => 'completed',
            ]);

            Log::info('AI Completed Successfully', [
                'report_id' => $labReport->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('ProcessLabReportAI Failed', [
                'report_id' => $labReport->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            $labReport->update([
                'analysis_status' => 'failed',
            ]);

            throw $e;
        }
    }
}
