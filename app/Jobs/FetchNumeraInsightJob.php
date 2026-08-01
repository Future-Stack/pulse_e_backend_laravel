<?php

namespace App\Jobs;

use App\Models\NumeraInsight;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchNumeraInsightJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    protected int $insightId;

    public function __construct(int $insightId)
    {
        $this->insightId = $insightId;
    }

    public function handle(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Find Numera Insight
        |--------------------------------------------------------------------------
        */

        $insight = NumeraInsight::find($this->insightId);

        if (! $insight) {

            Log::error('Numera Insight not found.', [
                'insight_id' => $this->insightId,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Get User ID
        |--------------------------------------------------------------------------
        */

        $userId = $insight->user_id;

        try {

            /*
            |--------------------------------------------------------------------------
            | pending -> processing
            |--------------------------------------------------------------------------
            */

            $insight->update([
                'status' => 'processing',
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI API URL
            |--------------------------------------------------------------------------
            */

            $url = config('services.ai.base_url')
                . '/api/numera-insight';

            /*
            |--------------------------------------------------------------------------
            | Call AI API
            |--------------------------------------------------------------------------
            |
            | user_id is required as query parameter.
            |
            */

            Log::info('Calling Numera Insight AI API.', [
                'url' => $url,
                'user_id' => $userId,
                'insight_id' => $insight->id,
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

            Log::info('Numera Insight AI Response.', [
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
            | Validate Numera Insight
            |--------------------------------------------------------------------------
            */

            if (! isset($data['numera_insight'])) {

                throw new \Exception(
                    'numera_insight key missing from AI response.'
                );
            }

            $result = $data['numera_insight'];

            /*
            |--------------------------------------------------------------------------
            | Save AI Result
            |--------------------------------------------------------------------------
            */

            $insight->update([

                'title' =>
                    $result['title'] ?? null,

                'tag' =>
                    $result['tag'] ?? null,

                'eyebrow' =>
                    $result['eyebrow'] ?? null,

                'headline' =>
                    $result['headline'] ?? null,

                'description' =>
                    $result['description'] ?? null,

                'cycle_day' =>
                    $result['cycle_day'] ?? null,

                'theme' =>
                    $result['theme'] ?? null,

                'priority' =>
                    $result['priority'] ?? null,

                'status' =>
                    'completed',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Success Log
            |--------------------------------------------------------------------------
            */

            Log::info('Numera Insight completed successfully.', [
                'insight_id' => $insight->id,
                'user_id' => $userId,
            ]);

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Failed
            |--------------------------------------------------------------------------
            */

            Log::error('Numera Insight Job Failed.', [

                'insight_id' =>
                    $this->insightId,

                'user_id' =>
                    $userId,

                'message' =>
                    $e->getMessage(),

                'file' =>
                    $e->getFile(),

                'line' =>
                    $e->getLine(),
            ]);

            $insight->update([
                'status' => 'failed',
            ]);

            throw $e;
        }
    }
}

