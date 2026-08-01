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
        /*
        |--------------------------------------------------------------------------
        | Find Smart Analysis
        |--------------------------------------------------------------------------
        */

        $smartAnalysis = SmartAnalysis::find($this->smartAnalysisId);

        if (! $smartAnalysis) {

            Log::error('SmartAnalysis not found.', [
                'smart_analysis_id' => $this->smartAnalysisId,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | User ID
        |--------------------------------------------------------------------------
        */

        $userId = $smartAnalysis->user_id;

        try {

            /*
            |--------------------------------------------------------------------------
            | pending -> processing
            |--------------------------------------------------------------------------
            */

            $smartAnalysis->update([
                'status' => 'processing',
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API URL
            |--------------------------------------------------------------------------
            */

            $url = config('services.ai.base_url')
                . '/api/smart-analysis';

            /*
            |--------------------------------------------------------------------------
            | Call Smart Analysis AI API
            |--------------------------------------------------------------------------
            |
            | AI API requires user_id as query parameter.
            |
            */

            Log::info('Calling Smart Analysis AI API.', [
                'url' => $url,
                'user_id' => $userId,
                'smart_analysis_id' => $smartAnalysis->id,
            ]);

            $response = Http::retry(3, 2000)
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(300)
                ->get($url, [

                    'user_id' => $userId,

                ]);

            /*
            |--------------------------------------------------------------------------
            | Log Response
            |--------------------------------------------------------------------------
            */

            Log::info('Smart Analysis Response.', [
                'status' => $response->status(),
                'user_id' => $userId,
                'body' => $response->body(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Validate Response
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {

                throw new \Exception(
                    'AI API Error: '
                    . $response->status()
                    . ' '
                    . $response->body()
                );
            }

            $data = $response->json();

            /*
            |--------------------------------------------------------------------------
            | Validate Smart Analysis Data
            |--------------------------------------------------------------------------
            */

            if (! isset($data['smart_analysis'])) {

                throw new \Exception(
                    'smart_analysis key missing.'
                );
            }

            $analysis = $data['smart_analysis'];

            /*
            |--------------------------------------------------------------------------
            | Save AI Result
            |--------------------------------------------------------------------------
            */

            $smartAnalysis->update([

                'title' =>
                    $analysis['title'] ?? null,

                'alerts' =>
                    $analysis['alerts'] ?? [],

                'status' =>
                    'completed',

            ]);

            /*
            |--------------------------------------------------------------------------
            | Success Log
            |--------------------------------------------------------------------------
            */

            Log::info('Smart Analysis completed.', [
                'smart_analysis_id' => $smartAnalysis->id,
                'user_id' => $userId,
            ]);

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Failed
            |--------------------------------------------------------------------------
            */

            Log::error('Smart Analysis failed.', [

                'smart_analysis_id' =>
                    $this->smartAnalysisId,

                'user_id' =>
                    $userId,

                'message' =>
                    $e->getMessage(),

                'file' =>
                    $e->getFile(),

                'line' =>
                    $e->getLine(),
            ]);

            $smartAnalysis->update([
                'status' => 'failed',
            ]);

            throw $e;
        }
    }
}

