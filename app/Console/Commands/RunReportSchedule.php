<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Services\Reports\ReportFileGenerator;
use App\Services\Reports\ReportScheduleRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Runs ONE report schedule (normally started by `reports:send-scheduled`).
 * Runs in its own process on purpose: it logs in as the schedule owner and
 * binds their company for the rest of the process.
 *
 * --dry-run builds the file into storage/app/report-schedules-dry-run/ without
 * mailing or logging anything (for checking a schedule by hand).
 */
class RunReportSchedule extends Command
{
    protected $signature = 'reports:run-schedule {id : report_schedules.id} {--trigger=schedule : schedule|manual} {--dry-run}';

    protected $description = 'Build and email one scheduled report now';

    public function handle(ReportScheduleRunner $runner, ReportFileGenerator $generator): int
    {
        $schedule = ReportSchedule::query()->find((int) $this->argument('id'));
        if (! $schedule) {
            $this->error('Schedule not found.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($schedule, $generator);
        }

        $trigger = $this->option('trigger') === 'manual' ? 'manual' : 'schedule';
        $run = $runner->run($schedule, $trigger);
        $this->line($run->status === 'sent'
            ? "sent {$run->file_name} (".number_format((int) $run->file_size).' bytes) to '.count((array) $run->recipients).' recipient(s)'.($run->error ? " — {$run->error}" : '')
            : "failed — {$run->error}");

        return $run->status === 'sent' ? self::SUCCESS : self::FAILURE;
    }

    private function dryRun(ReportSchedule $schedule, ReportFileGenerator $generator): int
    {
        $tenant = \App\Models\Tenant::query()->findOrFail($schedule->tenant_id);
        app()->instance('current_tenant', $tenant);
        $owner = \App\Models\User::query()->where('tenant_id', $tenant->id)->findOrFail($schedule->created_by);

        $file = $generator->generate($schedule, $owner, $tenant, CarbonImmutable::now());
        $dir = storage_path('app/report-schedules-dry-run');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.$file['filename'];
        file_put_contents($path, $file['content']);
        $this->info("{$file['period_label']}: {$path} (".strlen($file['content']).' bytes, '.$file['mime'].')');

        return self::SUCCESS;
    }
}
