<?php

namespace App\Http\Controllers\Broadcast;

use App\Http\Controllers\Controller;
use App\Models\BroadcastRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Recipient-facing web notification center — every authenticated user gets
 * this, regardless of the `broadcasts` RBAC grant (that gate is only on the
 * composer). Reads App\Models\BroadcastRecipient (this module's own source
 * of truth for read-tracking), not the generic `notifications` table
 * directly — the mobile app keeps using the generic table/API unmodified,
 * see App\Services\Broadcast\BroadcastDeliveryService for how the two stay
 * in sync.
 */
class BroadcastNotificationCenterController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $recipients = BroadcastRecipient::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('recipient_type', 'tenant_user')
            ->with('broadcast')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $recipients->through(fn (BroadcastRecipient $r) => $this->present($r)),
                'unread_count' => $this->unreadQuery($user)->count(),
            ]);
        }

        return view('client.broadcast.notifications', compact('recipients'));
    }

    public function unreadCount()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'unread_count' => $this->unreadQuery($user)->count(),
        ]);
    }

    public function markAsRead(int $id)
    {
        $user = Auth::user();

        $recipient = BroadcastRecipient::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if (! $recipient->read_at) {
            $recipient->update(['read_at' => now()]);

            if ($recipient->notification_id) {
                $user->notifications()->where('id', $recipient->notification_id)->first()?->markAsRead();
            }
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        $user = Auth::user();

        $unread = $this->unreadQuery($user)->get(['id', 'notification_id']);

        if ($unread->isEmpty()) {
            return response()->json(['success' => true]);
        }

        BroadcastRecipient::whereIn('id', $unread->pluck('id'))->update(['read_at' => now()]);

        $notificationIds = $unread->pluck('notification_id')->filter()->values();
        if ($notificationIds->isNotEmpty()) {
            $user->notifications()->whereIn('id', $notificationIds)->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    private function unreadQuery($user)
    {
        return BroadcastRecipient::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereNull('read_at');
    }

    private function present(BroadcastRecipient $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->broadcast->title ?? 'Notification',
            'message' => $r->broadcast->body ?? '',
            'action_url' => $r->broadcast->action_url ?? null,
            'action_label' => $r->broadcast->action_label ?? null,
            'priority' => $r->broadcast->priority ?? 'normal',
            'is_read' => ! is_null($r->read_at),
            'created_at_human' => $r->created_at->diffForHumans(),
        ];
    }
}
