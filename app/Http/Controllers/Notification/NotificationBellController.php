<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\BroadcastRecipient;
use App\Notifications\BroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

/**
 * The header bell (web panel): every notification the signed-in user has in
 * Laravel's `notifications` table — leave, attendance, expense, asset, task,
 * project, approvals and broadcasts alike (the same source the mobile app's
 * /api/notifications reads). Not feature-gated: every module writes here.
 *
 * Broadcasts also keep their own read flag on broadcast_recipients, so
 * marking one read here updates that too.
 */
class NotificationBellController extends Controller
{
    /** Full-page list ("View all"). */
    public function page(Request $request)
    {
        $status = in_array($request->query('status'), ['unread', 'read'], true) ? $request->query('status') : null;

        $notifications = Auth::user()->notifications()
            ->when($status === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($status === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $notifications->setCollection($notifications->getCollection()->map(fn ($n) => $this->present($n)));

        return view('client.notification.index', [
            'notifications' => $notifications,
            'status' => $status,
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    /** Latest few for the dropdown. */
    public function latest(Request $request)
    {
        $user = Auth::user();
        $limit = min(20, max(1, (int) $request->query('limit', 10)));

        return response()->json([
            'success' => true,
            'data' => $user->notifications()->latest()->limit($limit)->get()->map(fn ($n) => $this->present($n))->values(),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function unreadCount()
    {
        return response()->json(['success' => true, 'unread_count' => Auth::user()->unreadNotifications()->count()]);
    }

    public function markAsRead(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $this->syncBroadcastRead([$notification->id]);

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        $ids = $user->unreadNotifications()->pluck('id')->all();
        $user->unreadNotifications()->update(['read_at' => now()]);
        $this->syncBroadcastRead($ids);

        return response()->json(['success' => true]);
    }

    private function syncBroadcastRead(array $notificationIds): void
    {
        if ($notificationIds === []) {
            return;
        }

        BroadcastRecipient::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->whereIn('notification_id', $notificationIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /** Same title/message fallbacks as the mobile API (Api\Notification\NotificationController). */
    private function present(DatabaseNotification $n): array
    {
        $data = is_array($n->data) ? $n->data : (json_decode((string) $n->data, true) ?: []);

        return [
            'id' => $n->id,
            'type' => $data['type'] ?? 'general',
            'title' => $data['title'] ?? $data['message'] ?? 'Notification',
            'message' => $data['message'] ?? $data['body'] ?? '',
            'is_read' => $n->read_at !== null,
            'is_broadcast' => $n->type === BroadcastNotification::class,
            'created_at' => $n->created_at?->format('d M Y, h:i A'),
            'created_at_human' => $n->created_at?->diffForHumans(),
        ];
    }
}
