<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class NotificationController extends Controller
{
    public function fetchUserNotification(Request $request)
    {
        try {
            $user = Auth::user();

            // Fetch notifications sent by the current admin
            $notifications = $user->notifications()
                ->latest()
                ->get()
                ->map(function ($notification) {
                    $data = $notification->data;
                    return [
                        'id'          => $notification->id,
                        'title'       => $data['title'] ?? '',
                        'message'     => $data['message'] ?? '',
                        'type'        => ucfirst($data['type'] ?? 'Announcement'),
                        'recipients'  => $data['recipients_count'] ?? 1,
                        'sent_to'     => $data['sent_to_label'] ?? 'All Users',
                        'sent_at'     => $notification->created_at->format('Y-m-d h:i A'),
                        'read_at'     => $notification->read_at,
                        'status'      => 'delivered',
                    ];
                });

            return response()->json([
                'success' => true,
                'total'   => $notifications->count(),
                'data'    => $notifications,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Notification fetch failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications.'
            ], 500);
        }
    }
    public function fetchAdminNotification(Request $request)
    {
        try {
            // Group notifications by title, message, and type
            $notifications = DB::table('notifications')
                ->select(
                    DB::raw('MIN(id) as id'),
                    DB::raw('MAX(created_at) as sent_at'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.title")) as title'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.message")) as message'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.type")) as type'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.sent_to_label")) as sent_to'),
                    DB::raw('COUNT(*) as recipients'),
                    DB::raw('MAX(read_at) as read_at') // ✅ include read_at

                )
                ->where('type','App\Notifications\AdminIconNotification')
                ->groupByRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.title")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.message")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.type")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.sent_to_label"))')
                ->orderBy('sent_at', 'desc')
                ->get()
                ->map(function ($n) {
                    return [
                        'id'          => $n->id,
                        'title'       => $n->title,
                        'message'     => $n->message,
                        'type'        => ucfirst($n->type),
                        'recipients'  => $n->recipients,
                        'sent_to'     => $n->sent_to ?? 'All Users',
                        'sent_at'     => $n->sent_at ? Carbon::parse($n->sent_at)->diffForHumans()  : null,
                        'read_at'     => $n->read_at ? $n->read_at : null, // ✅ show read_at
                        'status'      => 'delivered',
                    ];
                });

            return response()->json([
                'success' => true,
                'total'   => $notifications->count(),
                'data'    => $notifications,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Notification grouping failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function fetchReadAdminNotification(Request $request)
    {
        try {
            // Group notifications by title, message, and type
            $notifications = DB::table('notifications')
                ->select(
                    DB::raw('MIN(id) as id'),
                    DB::raw('MAX(created_at) as sent_at'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.title")) as title'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.message")) as message'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.type")) as type'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.sent_to_label")) as sent_to'),
                    DB::raw('COUNT(*) as recipients')
                )
                ->where('type','App\Notifications\AdminIconNotification')
                ->whereNotNull('read_at') // ✅ only read notifications
                ->groupByRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.title")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.message")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.type")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.sent_to_label"))')
                ->orderBy('sent_at', 'desc')
                ->get()
                ->map(function ($n) {
                    return [
                        'id'          => $n->id,
                        'title'       => $n->title,
                        'message'     => $n->message,
                        'type'        => ucfirst($n->type),
                        'recipients'  => $n->recipients,
                        'sent_to'     => $n->sent_to ?? 'All Users',
                        'sent_at'     => $n->sent_at ? Carbon::parse($n->sent_at)->diffForHumans()  : null,
                        'status'      => 'delivered',
                    ];
                });

            return response()->json([
                'success' => true,
                'total'   => $notifications->count(),
                'data'    => $notifications,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Notification grouping failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead($id)
    {
        try {
            DB::table('notifications')
                ->where('id', $id)
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Mark single notification failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function markAllAsRead(Request $request)
    {
        try {
            DB::table('notifications')
                ->where('notifiable_id', $request->user()->id)
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Mark all notifications failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
