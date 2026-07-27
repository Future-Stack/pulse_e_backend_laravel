<?php

namespace App\Http\Controllers\ChatController;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function handleResponse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'session_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        $sessionId = $request->input('session_id');
        $userMessage = $request->input('message');

        $chatSession = ChatSession::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $userId]
        );

        ChatMessage::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'sender_type' => 'user',
            'message' => $userMessage,
        ]);

        $aiUrl = config('services.ai.mood_analyzer_url', 'https://female-mood-analyzer.onrender.com/api/chat/response');

        try {
            $aiResponse = Http::connectTimeout(10)
                ->timeout(60)
                ->retry(1, 3000, function ($exception, $request) {
                    // Only retry on connection issues (e.g. cold start), not on 4xx/5xx app responses
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                })
                ->post($aiUrl, [
                    'user_id' => (string) $userId,
                    'message' => $userMessage,
                    'session_id' => $sessionId,
                ]);

            if ($aiResponse->failed()) {
                Log::error('AI Service failed', [
                    'status' => $aiResponse->status(),
                    'body' => $aiResponse->body(),
                    'url' => $aiUrl,
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                ]);

                return response()->json([
                    'message' => 'AI Service connection failed',
                    'error' => $aiResponse->body()
                ], 502);
            }

            $responseData = $aiResponse->json();

            $aiResponseBody = $responseData['response'] ?? 'Sorry, I could not analyze your mood at this time.';
            $dataSummary = $responseData['data_summary'] ?? null;

            $aiMessage = ChatMessage::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'sender_type' => 'ai',
                'message' => $aiResponseBody,
                'data_summary' => $dataSummary,
            ]);

            return response()->json([
                'response' => $aiMessage->message,
                'session_id' => $sessionId,
                'timestamp' => $responseData['timestamp'] ?? $aiMessage->created_at->toISOString(),
                'data_summary' => $aiMessage->data_summary,
            ], 200);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('AI Service connection exception', [
                'error' => $e->getMessage(),
                'url' => $aiUrl,
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'message' => 'AI Service is warming up or unresponsive',
                'error' => 'The request timed out. Render server might be in sleep mode.'
            ], 504);

        } catch (\Exception $e) {
            Log::error('AI Service processing error', [
                'error' => $e->getMessage(),
                'url' => $aiUrl,
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'message' => 'AI Service processing error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}