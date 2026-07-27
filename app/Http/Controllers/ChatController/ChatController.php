<?php

namespace App\Http\Controllers\ChatController;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMoodAnalysis;
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
            return response()->json(['detail' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $sessionId = $request->input('session_id');
        $userMessage = $request->input('message');

        ChatSession::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $userId]
        );

        $userChatMessage = ChatMessage::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'sender_type' => 'user',
            'message' => $userMessage,
        ]);

        ProcessMoodAnalysis::dispatch($userId, $sessionId, $userMessage, $userChatMessage->id);

        return response()->json([
            'message' => 'Message received, processing your response.',
            'session_id' => $sessionId,
            'status' => 'processing',
        ], 202); // 202 Accepted - processing shuru hoyeche
    }

    public function getLatestMessages(Request $request, $sessionId)
    {
        $messages = ChatMessage::where('session_id', $sessionId)
            ->latest()
            ->take(10)
            ->get();

        return response()->json($messages);
    }
}