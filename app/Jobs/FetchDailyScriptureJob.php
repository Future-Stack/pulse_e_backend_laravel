<?php

namespace App\Jobs;

use App\Models\DailyScripture;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchDailyScriptureJob implements ShouldQueue
{
    use Queueable;

    protected int $dailyScriptureId;

    public function __construct(int $dailyScriptureId)
    {
        $this->dailyScriptureId = $dailyScriptureId;
    }

    public function handle(): void
    {
        $dailyScripture = DailyScripture::find($this->dailyScriptureId);

        if (!$dailyScripture) {
            Log::error('Daily Scripture record not found.');
            return;
        }

        try {

            $dailyScripture->update([
                'status' => 'processing',
            ]);

            $response = Http::withoutVerifying()
                ->acceptJson()
                ->timeout(300)
                ->get(config('services.ai.base_url') . '/api/daily-scripture');
                
            if (!$response->successful()) {
                throw new \Exception(
                    'AI Service Error: ' .
                    $response->status() .
                    ' ' .
                    $response->body()
                );
            }

            $result = $response->json();

            if (!isset($result['daily_scripture'])) {
                throw new \Exception('daily_scripture key not found.');
            }

            $scripture = $result['daily_scripture'];

            $dailyScripture->update([
                'title'          => $scripture['title'] ?? null,
                'scripture_date' => $scripture['date'] ?? null,
                'badge'          => $scripture['badge'] ?? null,
                'verse_text'     => $scripture['verse_text'] ?? null,
                'reference'      => $scripture['reference'] ?? null,
                'reason'         => $scripture['reason'] ?? null,
                'status'         => 'completed',
            ]);

        } catch (\Throwable $e) {

            Log::error('Daily Scripture Job Failed', [
                'message' => $e->getMessage(),
            ]);

            $dailyScripture->update([
                'status' => 'failed',
            ]);
        }
    }
}