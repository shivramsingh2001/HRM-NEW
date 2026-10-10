<?php

namespace App\Console\Commands;

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
        $this->info('Expired ' . $service->expireDue() . ' shift request(s).');

        return self::SUCCESS;
    }
}
