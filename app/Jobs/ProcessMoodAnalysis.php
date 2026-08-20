<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessMoodAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 100; // job er nijer max execution time (seconds), PHP-FPM/nginx er theke independent

    public function __construct(
        public int $userId,
        public string $sessionId,
        public string $userMessage,
        public int $userChatMessageId
    ) {}

    public function handle(): void
    {
        $aiUrl = config('services.ai.mood_analyzer_url') ?: (rtrim(config('services.ai.base_url', 'https://ai.fightthenumber.com'), '/') . '/api/chat/response');

        try {
            $aiResponse = Http::connectTimeout(10)
                ->timeout(90)
                ->retry(1, 3000, fn($e) => $e instanceof \Illuminate\Http\Client\ConnectionException)
                ->post($aiUrl, [
                    'user_id' => $this->userId,
                    'message' => $this->userMessage,
                    'session_id' => $this->sessionId,
                ]);

            if ($aiResponse->failed()) {
                Log::error('AI Service failed', [
                    'status' => $aiResponse->status(),
                    'body' => $aiResponse->body(),
                ]);

                ChatMessage::create([
                    'user_id' => $this->userId,
                    'session_id' => $this->sessionId,
                    'sender_type' => 'ai',
                    'message' => 'Sorry, I could not analyze your mood at this time.',
                    'status' => 'failed',
                ]);
                return;
            }

            $responseData = $aiResponse->json();

            ChatMessage::create([
                'user_id' => $this->userId,
                'session_id' => $this->sessionId,
                'sender_type' => 'ai',
                'message' => $responseData['response'] ?? 'Sorry, I could not analyze your mood at this time.',
                'data_summary' => $responseData['data_summary'] ?? null,
                'status' => 'completed',
            ]);

        } catch (\Exception $e) {
            Log::error('AI Service processing error', ['error' => $e->getMessage()]);

            ChatMessage::create([
                'user_id' => $this->userId,
                'session_id' => $this->sessionId,
                'sender_type' => 'ai',
                'message' => 'Sorry, the AI service is currently unavailable.',
                'status' => 'failed',
            ]);
        }
    }
}