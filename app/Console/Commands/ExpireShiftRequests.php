<?php

namespace App\Console\Commands;

use App\Models\ShiftRequest;
use App\Services\Shift\ShiftRequestService;
use Illuminate\Console\Command;

/**
 * Expires pending shift swap / change requests whose colleague didn't answer
 * in time (Company Policies → Shift Requests → colleague response hours) or
 * whose first shift has already started without a decision. Run hourly.
 */
class ExpireShiftRequests extends Command
{
    protected $signature = 'shift-requests:expire';

    protected $description = 'Expire pending shift swap / change requests that ran out of time';

    public function handle(ShiftRequestService $service): int
    {
        // Every company's due requests; each one carries its own tenant_id.
        $count = 0;
        ShiftRequest::withoutGlobalScopes()
            ->whereIn('status', ShiftRequest::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (ShiftRequest $req) use ($service, &$count) {
                $service->expire($req);
                $count++;
            });

        $this->info("Expired {$count} shift request(s).");

        return self::SUCCESS;
    }
}
