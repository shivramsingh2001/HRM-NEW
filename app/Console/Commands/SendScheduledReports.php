<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Services\Reports\ReportScheduleTiming;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Every minute (routes/console.php): finds report schedules that are due (or
 * had "Send now" pressed), claims each one, and runs it in its own process
 * (`reports:run-schedule`) so one company's login / tenant / caches never
 * leak into the next and a crashing report can't stop the others.
 *
 * Claiming = moving next_run_at to the following slot (or clearing
 * run_requested_at) with a compare-and-set update, so a schedule is sent once
 * even if two dispatchers overlap. A missed slot (server down) is sent once on
 * the next tick, then the schedule continues from now.
 */
class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled';

    protected $description = 'Send report schedules that are due (Reports → Scheduled Reports)';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $jobs = [];

        $requested = ReportSchedule::query()->whereNotNull('run_requested_at')->limit(100)->get(['id', 'run_requested_at']);
        foreach ($requested as $s) {
            $claimed = ReportSchedule::query()->whereKey($s->id)
                ->where('run_requested_at', $s->getRawOriginal('run_requested_at'))
                ->update(['run_requested_at' => null]);
            if ($claimed === 1) {
                $jobs[] = [$s->id, 'manual'];
            }
        }

        $due = ReportSchedule::query()->where('is_active', true)
            ->whereNotNull('next_run_at')->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')->limit(200)->get();
        foreach ($due as $s) {
            $next = ReportScheduleTiming::nextRunAfter($s, $now);
            $claimed = ReportSchedule::query()->whereKey($s->id)
                ->where('next_run_at', $s->getRawOriginal('next_run_at'))
                ->update(['next_run_at' => $next]);
            if ($claimed === 1) {
                $jobs[] = [$s->id, 'schedule'];
            }
        }

        foreach ($jobs as [$id, $trigger]) {
            $result = Process::path(base_path())->timeout(600)
                ->run([PHP_BINARY, 'artisan', 'reports:run-schedule', (string) $id, '--trigger='.$trigger]);
            $out = trim($result->output()."\n".$result->errorOutput());
            $this->line('['.now()->format('Y-m-d H:i:s')."] schedule #{$id} ({$trigger}): ".($out !== '' ? $out : 'exit '.$result->exitCode()));
        }

        return self::SUCCESS;
    }
}
