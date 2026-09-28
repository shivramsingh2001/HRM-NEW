<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Services\Broadcast\BroadcastDeliveryService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one chunk (<= 500) of a Broadcast's `broadcast_recipients` rows —
 * dispatched in a Bus::batch() by App\Services\Broadcast\
 * BroadcastDeliveryService::dispatchDelivery(). Bounded, explicit retry
 * shape mirrors App\Jobs\SendWebhookDelivery (self-redispatch with a delay
 * on failure, rather than relying on Laravel's default `tries`/backoff).
 */
class SendBroadcastBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Minutes to wait before retry attempt N (1-indexed). */
    private const SCHEDULE = [1, 5, 15];

    public int $tries = 1; // retry/scheduling handled explicitly below

    public function __construct(public int $broadcastId, public array $recipientRowIds, public int $attempt = 1)
    {
        $this->onQueue('broadcasts');
    }

    public function handle(BroadcastDeliveryService $delivery): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $broadcast = Broadcast::find($this->broadcastId);
        if (! $broadcast) {
            return;
        }

        try {
            $delivery->deliverChunk($broadcast, $this->recipientRowIds);
        } catch (\Throwable $e) {
            Log::warning('SendBroadcastBatchJob: chunk failed: ' . $e->getMessage(), [
                'broadcast_id' => $this->broadcastId,
                'attempt' => $this->attempt,
            ]);

            if ($this->attempt < count(self::SCHEDULE)) {
                self::dispatch($this->broadcastId, $this->recipientRowIds, $this->attempt + 1)
                    ->delay(now()->addMinutes(self::SCHEDULE[$this->attempt]))
                    ->onQueue('broadcasts');

                return;
            }

            // Exhausted retries — matches App\Jobs\SendWebhookDelivery's
            // convention: log permanently and return normally rather than
            // rethrowing, so this chunk's failure doesn't mark the whole
            // Bus::batch as failed (unaffected recipients in other chunks
            // already delivered fine; this chunk's rows just stay
            // delivered_at=null, visible in the broadcast's own stats).
            Log::error('SendBroadcastBatchJob: chunk permanently failed after ' . count(self::SCHEDULE) . ' attempts', [
                'broadcast_id' => $this->broadcastId,
                'recipient_row_ids' => $this->recipientRowIds,
            ]);
        }
    }
}
