<?php

namespace App\Console\Commands;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use Illuminate\Console\Command;

/**
 * Two idempotent, unrelated sweeps bundled in one scheduled command:
 *  1. Flip any `sent` broadcast whose expires_at has passed to `expired` —
 *     a pure status flip, re-running it is a no-op once done (nothing left
 *     to flip), matching ExpenseAdvanceReminders' idempotent-by-query shape.
 *  2. Recompute delivered_count/read_count/action_click_count on every
 *     `sent` broadcast from the live broadcast_recipients rows — these
 *     snapshot counters are deliberately NOT live-incremented per-event (to
 *     avoid hot-row UPDATE contention at scale), so this periodic pass is
 *     what keeps the broadcast history list's numbers reasonably fresh; the
 *     detail/stats page always computes exact numbers live regardless.
 */
class ExpireBroadcasts extends Command
{
    protected $signature = 'broadcast:expire';

    protected $description = 'Expire sent broadcasts past their expires_at and refresh delivery/read stat snapshots';

    public function handle(): int
    {
        // Recompute stats WHILE still `sent`, before the expiry flip below —
        // otherwise a broadcast expiring in this very run would never get a
        // final fresh snapshot (its recompute query only matches status=sent,
        // and by the time it'd run post-flip it's already `expired`).
        $recomputed = 0;
        Broadcast::where('status', 'sent')->select(['id'])->chunkById(200, function ($broadcasts) use (&$recomputed) {
            foreach ($broadcasts as $broadcast) {
                $stats = BroadcastRecipient::where('broadcast_id', $broadcast->id)
                    ->selectRaw('count(*) as total, count(delivered_at) as delivered, count(read_at) as `read`, count(action_clicked_at) as clicked')
                    ->first();

                Broadcast::where('id', $broadcast->id)->update([
                    'total_recipients' => $stats->total ?? 0,
                    'delivered_count' => $stats->delivered ?? 0,
                    'read_count' => $stats->read ?? 0,
                    'action_click_count' => $stats->clicked ?? 0,
                ]);

                $recomputed++;
            }
        });

        $expired = Broadcast::expirable()->update(['status' => 'expired']);

        $this->info("Refreshed stats on {$recomputed} sent broadcast(s); expired {$expired} of them.");

        return self::SUCCESS;
    }
}
