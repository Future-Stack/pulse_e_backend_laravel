<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\LabReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LabReportAIController extends Controller
{
    /**
     * Get AI Analysis (direct call, no queue)
     */
    public function show(LabReport $labReport)
    {
        try {
            return DB::transaction(function () use ($labReport) {

                // Step 0: Check if PDF exists
                if (!$labReport->lab_report) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Lab report PDF not found.',
                    ], 404);
                }

                // Step 1: Return from DB if already analyzed
                if (
                    $labReport->analysis_status === 'completed' &&
                    !empty($labReport->panel)
                ) {
                    // এখানে lab_report_url যোগ করা হলো
                    $labReport->lab_report_url = asset('storage/'.$labReport->lab_report);

                    return response()->json([
                        'success' => true,
                        'message' => 'AI report loaded from database.',
                        'data'    => $labReport,
                    ]);
                }

                // Step 2: Update status → processing
                $labReport->update([
                    'analysis_status' => 'processing',
                ]);

                // Step 3: External AI API call directly
                $response = Http::withoutVerifying()
                    ->acceptJson()
                    ->timeout(600)
                    ->post(config('services.ai_service.url') . '/api/summarize-pdf', [
                        'report_id'   => $labReport->id,
                        'source_path' => asset('storage/'.$labReport->lab_report),
                    ]);

                if (!$response->successful()) {
                    throw new \Exception('AI service failed. Status: '.$response->status().' Body: '.$response->body());
                }

                $result  = $response->json();
                $summary = $result['summary'] ?? [];

                // Step 4: Save AI result
                $labReport->update([
                    'panel'           => $summary['panel'] ?? null,
                    'biomarkers'      => $summary['biomarkers'] ?? null,
                    'ai_insights'     => $summary['ai_insights'] ?? null,
                    'next_steps'      => $summary['next_steps'] ?? null,
                    'analysis_status' => 'completed',
                ]);

                // fresh data + full URL
                $labReport = $labReport->fresh();
                $labReport->lab_report_url = asset('storage/'.$labReport->lab_report);

                return response()->json([
                    'success' => true,
                    'message' => 'AI analysis completed successfully.',
                    'data'    => $labReport,
                ]);
            });

        } catch (\Throwable $e) {
            Log::error('AI Analysis Error', [
                'message' => $e->getMessage(),
            ]);

            $labReport->update([
                'analysis_status' => 'failed',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
