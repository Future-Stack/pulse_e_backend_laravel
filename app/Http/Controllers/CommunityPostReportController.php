<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityPostReport;
use App\Models\User;
use App\Notifications\AdminIconNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class CommunityPostReportController extends Controller
{
    /**
     * POST /api/community/posts/{post}/report
     */
    public function store(Request $request, CommunityPost $post)
    {
        $validated = $request->validate([
            'report_cause' => 'required|in:spam,sexual_content,harassment,other',
            'comment'      => 'nullable|string|max:255',
        ]);

        $userId = Auth::id();

        // Prevent the same user reporting the same post more than once
        $existing = CommunityPostReport::where('post_id', $post->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already reported this post.'], 409);
        }

        $report = CommunityPostReport::create([
            'post_id'      => $post->id,
            'user_id'      => $userId,
            'comment'      => $validated['comment'] ?? null,
            'report_cause' => $validated['report_cause'],
        ]);

        //Send Admin Notification
        $admin = User::where('user_type', 'admin')->first();

        if ($admin) {
            Notification::send($admin, new AdminIconNotification([
                'type' => 'report',
                'title' => 'New Post Report',
                'message' => 'A new report has been posted.',
                'sender_id' => null,
            ]));
        }

        return response()->json([
            'message' => 'Report submitted. Our team will review this post.',
            'report'  => $report,
        ], 201);
    }

    /**
     * GET /api/admin/community/reports
     * Admin-only: list all reports, newest first.
     */
    public function index()
    {
        $reports = CommunityPostReport::query()
            ->with(['post:id,title,slug', 'user:id,full_name'])
            ->latest()
            ->paginate(20);

        return response()->json($reports);
    }
}
