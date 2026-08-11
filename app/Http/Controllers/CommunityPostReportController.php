<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityPostReport;
use App\Models\User;
use App\Notifications\AdminIconNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CommunityPostReportController extends Controller
{
    /**
     * POST /api/community/posts/{post}/report
     * Regular User: Submit a report for a post
     */
    public function store(Request $request, CommunityPost $post)
    {
        $validated = $request->validate([
            'report_cause' => 'required|in:spam,sexual_content,harassment,other',
            'comment'      => 'nullable|string|max:255',
        ]);

        $userId = Auth::id();

        // Check if report already exists
        $existing = CommunityPostReport::where('post_id', $post->id)
            ->where('user_id', $userId)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reported this post.'
            ], 409);
        }

        $report = CommunityPostReport::create([
            'post_id'      => $post->id,
            'user_id'      => $userId,
            'is_active'    => true,
            'comment'      => $validated['comment'] ?? null,
            'report_cause' => $validated['report_cause'],
        ]);

        // Send notification to ALL Admins
        $admins = User::where('user_type', 'admin')->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new AdminIconNotification([
                'type'      => 'report',
                'title'     => 'New Post Report',
                'message'   => 'A new report has been posted.',
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
     * Admin-only: List all reports with relationships
     */
    public function index()
    {
        if (! Auth::user()?->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized. Only admins can view reports.'
            ], 403);
        }

        $reports = CommunityPostReport::query()
            ->with(['post:id,title,slug,is_approved', 'user:id,full_name'])
            ->latest()
            ->paginate(20);

        return response()->json($reports);
    }

    /**
     * PATCH /api/admin/community/reports/{report}/approve
     * Admin-only: Confirms violation, hides post, and closes report
     */
    public function approve(CommunityPostReport $report)
    {
        // Admin authorization check
        if (! Auth::user()?->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized. Only admins can approve reports.'
            ], 403);
        }

        DB::transaction(function () use ($report) {
            // Close/Resolve the report
            $report->update(['is_active' => false]);

            // Hide the reported post from public view
            $report->post()->update(['is_approved' => false]);
        });

        return response()->json([
            'message' => 'Report approved. The post has been hidden.',
            'report'  => $report->fresh(['post']),
        ]);
    }

    /**
     * PATCH /api/admin/community/reports/{report}/decline
     * Admin-only: Rejects report, keeps post live, and closes report
     */
    public function decline(CommunityPostReport $report)
    {
        // Admin authorization check
        if (! Auth::user()?->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized. Only admins can decline reports.'
            ], 403);
        }

        DB::transaction(function () use ($report) {
            // Close/Resolve the report
            $report->update(['is_active' => false]);

            // Keep/Ensure the post is live
            $report->post()->update(['is_approved' => true]);
        });

        return response()->json([
            'message' => 'Report declined.',
            'report'  => $report->fresh(['post']),
        ]);
    }
}