<?php

namespace App\Observers;

use App\Models\BroadcastRecipient;
use App\Notifications\BroadcastNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

/**
 * Closes the read-state sync loop's other direction for the Broadcast
 * Notification module: the web notification center already sets both
 * broadcast_recipients.read_at AND the linked notifications row's read_at
 * in one request (see App\Http\Controllers\Broadcast\
 * BroadcastNotificationCenterController::markAsRead()). The mobile API
 * (App\Http\Controllers\Api\Notification\NotificationController) only ever
 * touches the generic `notifications` table and stays completely
 * unmodified — this observer is what propagates a mobile-driven read back
 * onto broadcast_recipients, so either surface can mark a broadcast read
 * and the two stay in sync.
 *
 * Narrowly scoped (type check first) — a cheap no-op for every other
 * Notification subclass in the app.
 */
class DatabaseNotificationObserver
{
    public function updated(DatabaseNotification $notification): void
    {
        if ($notification->type !== BroadcastNotification::class) {
            return;
        }

        if (! $notification->wasChanged('read_at') || is_null($notification->read_at)) {
            return;
        }

        try {
            BroadcastRecipient::where('notification_id', $notification->id)
                ->whereNull('read_at')
                ->update(['read_at' => $notification->read_at]);
        } catch (\Throwable $e) {
            Log::warning('DatabaseNotificationObserver: ' . $e->getMessage(), [
                'notification_id' => $notification->id,
            ]);
        }
    }
}
