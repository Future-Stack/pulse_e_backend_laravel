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


    public int $tries = 3;

    public int $timeout = 600;


    protected int $dailyScriptureId;


    public function __construct(int $dailyScriptureId)
    {
        $this->dailyScriptureId = $dailyScriptureId;
    }


    public function handle(): void
    {
        $dailyScripture = DailyScripture::find($this->dailyScriptureId);


        if (! $dailyScripture) {

            Log::error('Daily Scripture record not found.', [
                'daily_scripture_id' => $this->dailyScriptureId,
            ]);

            return;
        }


        try {

            // Processing start
            $dailyScripture->update([
                'status' => 'processing',
            ]);


            $url = config('services.ai.base_url')
                . '/api/daily-scripture';


            Log::info('Calling Daily Scripture AI API.', [
                'url' => $url,
                'daily_scripture_id' => $dailyScripture->id,
                'user_id' => $dailyScripture->user_id,
            ]);


            $response = Http::retry(3, 2000)
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(300)
                ->get($url);



            Log::info('Daily Scripture API Response.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);



            if (! $response->successful()) {

                throw new \Exception(
                    'AI Service Error: '
                    . $response->status()
                    . ' '
                    . $response->body()
                );
            }



            $result = $response->json();



            if (! isset($result['daily_scripture'])) {

                throw new \Exception(
                    'daily_scripture key not found in AI response.'
                );
            }



            $scripture = $result['daily_scripture'];



            $dailyScripture->update([

                'title' => $scripture['title'] ?? null,

                'scripture_date' => $scripture['date'] ?? null,

                'badge' => $scripture['badge'] ?? null,

                'verse_text' => $scripture['verse_text'] ?? null,

                'reference' => $scripture['reference'] ?? null,

                'reason' => $scripture['reason'] ?? null,

                'status' => 'completed',
            ]);



            Log::info('Daily Scripture generated successfully.', [
                'daily_scripture_id' => $dailyScripture->id,
            ]);



        } catch (\Throwable $e) {


            Log::error('Daily Scripture Job Failed.', [

                'daily_scripture_id' => $this->dailyScriptureId,

                'message' => $e->getMessage(),

                'file' => $e->getFile(),

                'line' => $e->getLine(),
            ]);



            $dailyScripture->update([
                'status' => 'failed',
            ]);


            throw $e;
        }
    }
}