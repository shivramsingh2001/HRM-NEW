<?php

namespace App\Services\Broadcast;

use App\Jobs\SendBroadcastBatchJob;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers an already-snapshotted Broadcast. Per chunk of undelivered
 * `broadcast_recipients`, dual-writes via Laravel's Notification system
 * (Illuminate\Support\Facades\Notification::send(), the same batch
 * primitive App\Services\Approvals\ApprovalService::notifyLevel() already
 * uses — try/catch, non-blocking, never allowed to break the send), then
 * reconciles `notification_id` back onto the snapshot rows (Notification::
 * send() doesn't return created ids, so this is a small post-send lookup,
 * not a mid-flight capture).
 *
 * Two entry points:
 *  - dispatchDelivery(): Phase B, the normal path — queues one
 *    App\Jobs\SendBroadcastBatchJob per 500-recipient chunk inside a
 *    Bus::batch(), so the composer's web request returns immediately and
 *    delivery happens off-thread. Requires QUEUE_CONNECTION != sync to
 *    actually run asynchronously (this app now runs `database`).
 *  - deliver(): synchronous whole-broadcast send, kept for small manual
 *    sends/tests where queuing is unnecessary overhead.
 */
class BroadcastDeliveryService
{
    private const CHUNK_SIZE = 500;

    public function dispatchDelivery(Broadcast $broadcast): void
    {
        $chunks = BroadcastRecipient::where('broadcast_id', $broadcast->id)
            ->where('recipient_type', 'tenant_user')
            ->whereNull('delivered_at')
            ->pluck('id')
            ->chunk(self::CHUNK_SIZE);

        if ($chunks->isEmpty()) {
            $broadcast->update(['status' => 'sent', 'sent_at' => now()]);

            return;
        }

        $jobs = $chunks->map(fn ($ids) => new SendBroadcastBatchJob($broadcast->id, $ids->values()->all()))->all();

        Bus::batch($jobs)
            ->name('broadcast-' . $broadcast->id)
            ->onQueue('broadcasts')
            ->then(function () use ($broadcast) {
                $broadcast->fresh()->update(['status' => 'sent', 'sent_at' => now()]);
            })
            ->catch(function (\Throwable $e) use ($broadcast) {
                Log::error('BroadcastDeliveryService: batch failed: ' . $e->getMessage(), [
                    'broadcast_id' => $broadcast->id,
                ]);
            })
            ->dispatch();
    }

    public function deliver(Broadcast $broadcast): void
    {
        $broadcast->recipients()
            ->where('recipient_type', 'tenant_user')
            ->whereNull('delivered_at')
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function ($recipients) use ($broadcast) {
                $this->deliverRecipientRows($broadcast, $recipients->pluck('id')->all());
            }, 'id');

        $broadcast->update(['status' => 'sent', 'sent_at' => now()]);
    }

    /**
     * Delivers exactly the given broadcast_recipients row ids — the unit of
     * work App\Jobs\SendBroadcastBatchJob processes per chunk.
     */
    public function deliverChunk(Broadcast $broadcast, array $recipientRowIds): void
    {
        $this->deliverRecipientRows($broadcast, $recipientRowIds);
    }

    private function deliverRecipientRows(Broadcast $broadcast, array $recipientRowIds): void
    {
        if (! $recipientRowIds) {
            return;
        }

        $userIds = BroadcastRecipient::whereIn('id', $recipientRowIds)
            ->where('recipient_type', 'tenant_user')
            ->whereNull('delivered_at')
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! $userIds) {
            return;
        }

        $users = User::whereIn('id', $userIds)->get();
        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, new BroadcastNotification($broadcast));
        } catch (\Throwable $e) {
            Log::warning('BroadcastDeliveryService: send failed: ' . $e->getMessage(), [
                'broadcast_id' => $broadcast->id,
            ]);
        }

        $this->reconcileNotificationIds($broadcast, $userIds);

        BroadcastRecipient::where('broadcast_id', $broadcast->id)
            ->whereIn('user_id', $userIds)
            ->update([
                'delivered_at' => now(),
                'channel_status' => ['database' => 'delivered'],
            ]);
    }

    private function reconcileNotificationIds(Broadcast $broadcast, array $userIds): void
    {
        try {
            $notificationIdsByUser = DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('type', BroadcastNotification::class)
                ->whereIn('notifiable_id', $userIds)
                ->whereRaw("JSON_EXTRACT(data, '$.broadcast_id') = ?", [$broadcast->id])
                ->pluck('id', 'notifiable_id');

            foreach ($notificationIdsByUser as $userId => $notificationId) {
                BroadcastRecipient::where('broadcast_id', $broadcast->id)
                    ->where('user_id', $userId)
                    ->update(['notification_id' => $notificationId]);
            }
        } catch (\Throwable $e) {
            Log::warning('BroadcastDeliveryService: notification_id reconcile failed: ' . $e->getMessage(), [
                'broadcast_id' => $broadcast->id,
            ]);
        }
    }
}
