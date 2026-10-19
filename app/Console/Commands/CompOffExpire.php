<?php

namespace App\Console\Commands;

use App\Services\Leave\CompOffService;
use Illuminate\Console\Command;

/** Daily: lapse the unused part of comp-off credits past their expiry date (CompOffService::expire). */
class CompOffExpire extends Command
{
    protected $signature = 'comp-off:expire {--date= : Treat this day (Y-m-d) as today}';

    protected $description = 'Lapse unused comp-off days whose expiry date has passed';

    public function handle(CompOffService $compOff): int
    {
        $n = $compOff->expire($this->option('date') ?: null);
        $this->info("Comp-off credits expired: {$n}.");

        return self::SUCCESS;
    }
}
