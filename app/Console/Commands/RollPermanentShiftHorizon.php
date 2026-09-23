<?php

namespace App\Console\Commands;

use App\Services\Shift\ShiftMaterializer;
use Illuminate\Console\Command;

/**
 * Tops up every active Permanent shift assignment's materialized user_shifts
 * cache to today+{ShiftMaterializer::PERMANENT_HORIZON_DAYS}, for every
 * tenant with custom_shifts_enabled=1. Run daily. This is what lets an admin
 * assign a Permanent shift once and never have to re-assign it — without this
 * command the cache would stop extending 120 days after the assignment was
 * created.
 */
class RollPermanentShiftHorizon extends Command
{
    protected $signature = 'shift:roll-permanent-horizon';

    protected $description = 'Extend every active Permanent shift assignment\'s materialized user_shifts cache to the rolling horizon';

    public function handle(ShiftMaterializer $materializer): int
    {
        $result = $materializer->rollHorizon();

        $this->info(sprintf(
            'Rolled permanent shift horizon: %d tenant(s), %d assignment(s), %d row(s) written/updated.',
            $result['tenants'],
            $result['assignments'],
            $result['rows']
        ));

        return self::SUCCESS;
    }
}
