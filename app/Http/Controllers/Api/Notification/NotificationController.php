<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    
     public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $query = $user->notifications();
            
            // Filter by read/unread
            if ($request->has('status')) {
                if ($request->status === 'read') {
                    $query->whereNotNull('read_at');
                } elseif ($request->status === 'unread') {
                    $query->whereNull('read_at');
                }
            }
            
            // Filter by type
            if ($request->has('type') && $request->type !== 'all') {
                $query->whereRaw("JSON_EXTRACT(data, '$.type') = ?", [$request->type]);
            }
            
            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));
            
            $formattedNotifications = $notifications->map(function($notification) {
                $data = is_string($notification->data) 
                    ? json_decode($notification->data, true) 
                    : $notification->data;
                
                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? 'general',
                    'title' => $data['title'] ?? $data['message'] ?? 'Notification',
                    'message' => $data['message'] ?? $data['body'] ?? 'You have a new notification',
                    // 'data' => $data,
                    'is_read' => !is_null($notification->read_at),
                    'read_at' => $notification->read_at,
                    // 'created_at' => $notification->created_at->toISOString(),
                    'created_at_human' => $notification->created_at->diffForHumans()
                ];
            });
            
            return response()->json([
                'success' => true,
                'message' => "Notifications fetched Successfully.",
                'data' => $formattedNotifications,
                'pagination' => [
                    'unread_count' => $user->unreadNotifications()->count(),
                    'total_count' => $user->notifications()->count(),
                    'current_page' => $notifications->currentPage(),
                    'first_page_url' => $notifications->url(1),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'prev_page_url' => $notifications->previousPageUrl(),
                    'next_page_url' => $notifications->nextPageUrl(),
                    'total' => $notifications->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            report($e);
          
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Get the count of unread notifications.
     */
    public function unreadCount()
    {
        $user = Auth::user();
        $count = $user->unreadNotifications->count();

        return response()->json([
            'success' => true,
            'message' => 'Data fetched Successfully.',
            'unread_count' => $count],200);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read'
            ],200);
    }

    /**
     * Mark all notifications as read for the user.
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.'],200);
    }

    /**
     * Delete a specific notification.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.'],200);
    }

    /**
     * Delete all read notifications for the user.
     */
    public function destroyAllRead()
    {
        $user = Auth::user();
        $user->notifications()->whereNotNull('read_at')->delete();

        return response()->json([
             'success' => true,
            'message' => 'All read notifications cleared.'],200);
    }
}