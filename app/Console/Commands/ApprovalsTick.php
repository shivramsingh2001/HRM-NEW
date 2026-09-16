<?php

namespace App\Console\Commands;

use App\Services\Approvals\ApprovalService;
use Illuminate\Console\Command;

/**
 * Tier 2 / T2-A — apply SLA breaches on pending approval requests.
 * Schedule every 15 minutes.
 */
class ApprovalsTick extends Command
{
    protected $signature = 'approvals:tick';

    protected $description = 'Process SLA breaches on pending approval requests (notify / escalate / auto-approve).';

    public function handle(ApprovalService $service): int
    {
        $n = $service->tick();
        $this->info("Processed {$n} SLA breach(es).");

        return self::SUCCESS;
    }
}
