<?php

namespace App\Console\Commands;

use App\Models\Broadcast;
use App\Models\Tenant;
use App\Services\Broadcast\BroadcastComposerService;
use App\Services\Broadcast\BroadcastDeliveryService;
use Illuminate\Console\Command;

/**
 * Fires every Broadcast whose scheduled_at has arrived.
 *
 * Deliberately RE-RESOLVES the audience at send time, not at compose time —
 * filters describe intent ("everyone in Sales"), so a scheduled broadcast
 * should pick up whoever currently matches when it actually fires (a new
 * hire added after scheduling is included; someone offboarded since is
 * automatically excluded since BroadcastAudienceResolver only ever matches
 * status=1 users). An explicit user_ids[] pick is a fixed id list; if one no
 * longer resolves to an active user, snapshotRecipients() simply won't
 * produce a row for them (App\Models\User::where('status',1) already
 * excludes them) — no special-casing needed here, matching the
 * ExpenseAdvanceReminders active-user-guard idiom.
 *
 * `app('current_tenant')` isn't bound outside an HTTP request, so
 * BroadcastAudienceResolver's tenant scoping (which relies on
 * TenantTrait reading that binding) is provided per-broadcast here instead.
 */
class SendScheduledBroadcasts extends Command
{
    protected $signature = 'broadcast:send-scheduled {--dry-run : List what would be sent, send nothing}';

    protected $description = 'Send every broadcast whose scheduled_at has arrived';

    public function handle(BroadcastComposerService $composer, BroadcastDeliveryService $delivery): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        Broadcast::dueToSend()->where('origin', 'tenant_admin')->get()->each(function (Broadcast $broadcast) use ($composer, $delivery, $dryRun, &$sent) {
            $tenant = Tenant::find($broadcast->origin_tenant_id);
            if (! $tenant) {
                $this->warn("Broadcast #{$broadcast->id}: origin tenant {$broadcast->origin_tenant_id} not found, skipping.");

                return;
            }

            $this->line(($dryRun ? '[dry] ' : '') . "Broadcast #{$broadcast->id} \"{$broadcast->title}\" (tenant {$tenant->id})");

            if ($dryRun) {
                $sent++;

                return;
            }

            app()->instance('current_tenant', $tenant);

            $broadcast->update(['status' => 'sending']);
            $composer->snapshotRecipients($broadcast, $broadcast->audience_filters);
            $delivery->dispatchDelivery($broadcast);

            $sent++;
        });

        $this->info(sprintf('%s%d broadcast(s) %s.', $dryRun ? '[DRY RUN] ' : '', $sent, $dryRun ? 'would be sent' : 'sent'));

        return self::SUCCESS;
    }
}
