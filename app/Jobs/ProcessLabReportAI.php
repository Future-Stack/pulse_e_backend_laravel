<?php

namespace App\Jobs;

use App\Models\LabReport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessLabReportAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $labReport;

    public function __construct(LabReport $labReport)
    {
        $this->labReport = $labReport;
    }

    public function handle()
    {
        try {
            $response = Http::retry(3, 2000) // 3 বার চেষ্টা করবে, প্রতি বার 2s gap
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(600)
                ->post('https://female-mood-analyzer.onrender.com/api/summarize-pdf', [
                    'report_id'   => $this->labReport->id,
                    'source_path' => asset('storage/'.$this->labReport->lab_report),
                ]);

            if (!$response->successful()) {
                throw new \Exception('AI service failed. Status: '.$response->status().' Body: '.$response->body());
            }

            $result  = $response->json();
            $summary = $result['summary'] ?? [];

            $this->labReport->update([
                'panel'           => $summary['panel'] ?? null,
                'biomarkers'      => $summary['biomarkers'] ?? null,
                'ai_insights'     => $summary['ai_insights'] ?? null,
                'next_steps'      => $summary['next_steps'] ?? null,
                'analysis_status' => 'completed',
            ]);

        } catch (\Throwable $e) {
            Log::error('AI Analysis Error', [
                'message' => $e->getMessage(),
            ]);

            $this->labReport->update([
                'analysis_status' => 'failed',
            ]);
        }
    }
}
