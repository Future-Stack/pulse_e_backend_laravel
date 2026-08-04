<?php

namespace App\Http\Controllers\ChatController;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMoodAnalysis;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function handleResponse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'session_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['detail' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $userMessage = $request->input('message');
        $sessionId = $request->input('session_id') ?? $request->query('session_id');

        $chatSession = null;
        if ($sessionId) {
            $chatSession = ChatSession::where('session_id', $sessionId)
                ->where('user_id', $userId)
                ->first();
        }

        if (!$chatSession) {
            $sessionId = (string) Str::uuid();
            $chatSession = ChatSession::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);
        } else {
            $chatSession->touch();
        }

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
        ], 202);
    }

    public function getUserSessions()
    {
        $userId = Auth::id();

        $sessions = ChatSession::where('user_id', $userId)
            ->with(['latestMessage'])
            ->latest('updated_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'sessions' => $sessions
        ], 200);
    }

    public function getLatestMessages(Request $request, $sessionId = null)
    {
        $sessionId = $sessionId ?? $request->query('session_id') ?? $request->input('session_id');

        if (!$sessionId) {
            return response()->json(['message' => 'session_id is required'], 400);
        }

        $userId = Auth::id();

        $sessionExists = ChatSession::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->exists();

        if (!$sessionExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or session not found.'
            ], 403);
        }

        $messages = ChatMessage::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->oldest() // 'created_at' asc
            ->get();

        return response()->json([
            'status' => 'success',
            'messages' => $messages
        ], 200);
    }
}