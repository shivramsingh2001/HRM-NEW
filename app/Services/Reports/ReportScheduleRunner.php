<?php

namespace App\Services\Reports;

use App\Exceptions\ReportScheduleException;
use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleRun;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Runs one report schedule: checks the owner may still get the report,
 * builds the file (ReportFileGenerator), emails it to every recipient, writes
 * the run log and pauses the schedule when it can't succeed until fixed.
 * Called by `reports:run-schedule` in its own process (see ReportFileGenerator).
 */
class ReportScheduleRunner
{
    /** Attachments above this are not sent; the mail says to download it from the app instead. */
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    /** Roles that may own a schedule (decision 2026-11-10: Admin + HR). */
    public const OWNER_ROLES = ['admin', 'hr'];

    public function __construct(private ReportFileGenerator $generator) {}

    public function run(ReportSchedule $schedule, string $trigger): ReportScheduleRun
    {
        $started = CarbonImmutable::now();
        $run = new ReportScheduleRun([
            'tenant_id' => $schedule->tenant_id,
            'report_schedule_id' => $schedule->id,
            'trigger' => $trigger,
            'status' => 'failed',
            'recipients' => $schedule->recipients,
            'started_at' => $started,
        ]);

        try {
            [$owner, $tenant] = $this->checkOwner($schedule);
            $file = $this->generator->generate($schedule, $owner, $tenant, $started);
            $run->period_label = $file['period_label'];
            $run->file_name = $file['filename'];
            $run->file_size = strlen($file['content']);

            $failed = $this->mailAll($schedule, $tenant, $file);
            if (count($failed) === count($schedule->recipients)) {
                throw new ReportScheduleException('The email could not be sent: '.reset($failed));
            }
            $run->status = 'sent';
            $run->error = $failed === [] ? null : 'Not delivered to: '.implode('; ', array_map(
                fn ($email, $why) => "{$email} ({$why})", array_keys($failed), $failed
            ));
        } catch (ReportScheduleException $e) {
            $run->error = $e->getMessage();
            if ($e->pause) {
                $this->pause($schedule, $e->getMessage());
            }
        } catch (HttpExceptionInterface $e) {
            $run->error = 'The report was refused: '.($e->getMessage() ?: 'HTTP '.$e->getStatusCode());
            if ($e->getStatusCode() === 403) {
                $this->pause($schedule, $run->error);
            }
        } catch (Throwable $e) {
            report($e);
            $run->error = 'Unexpected error while building the report: '.Str::limit($e->getMessage(), 300);
        }

        $run->finished_at = CarbonImmutable::now();
        $run->save();

        $schedule->forceFill([
            'last_run_at' => $started,
            'last_status' => $run->status,
            'last_error' => $run->error ? Str::limit($run->error, 490) : null,
        ])->save();
        $this->prune($schedule);

        return $run;
    }

    /** @return array{0: User, 1: Tenant} */
    private function checkOwner(ReportSchedule $schedule): array
    {
        $tenant = Tenant::query()->find($schedule->tenant_id);
        if (! $tenant || ! in_array($tenant->status, ['active', 'trial'], true)) {
            throw new ReportScheduleException('The company account is not active.', true);
        }
        app()->instance('current_tenant', $tenant);

        $owner = User::query()->where('tenant_id', $tenant->id)->find($schedule->created_by);
        if (! $owner || (int) $owner->status !== 1) {
            throw new ReportScheduleException('The person who created this schedule is no longer active. Edit it to take it over.', true);
        }
        if (! in_array($owner->role, self::OWNER_ROLES, true)) {
            throw new ReportScheduleException('The person who created this schedule is no longer Admin or HR. Edit it to take it over.', true);
        }
        $why = ReportCatalog::denialReason($schedule->report_key, $owner);
        if ($why !== null) {
            throw new ReportScheduleException($why, true);
        }

        return [$owner, $tenant];
    }

    /**
     * One mail per recipient, so outside addresses never see each other.
     *
     * @param  array{content: string, filename: string, mime: string, period_label: string}  $file
     * @return array<string, string> email => reason, for the ones that failed
     */
    private function mailAll(ReportSchedule $schedule, Tenant $tenant, array $file): array
    {
        $tooLarge = strlen($file['content']) > self::MAX_ATTACHMENT_BYTES;
        $failed = [];
        foreach ($schedule->recipients as $email) {
            try {
                Mail::to($email)->send(new ScheduledReportMail(
                    companyName: (string) ($tenant->company_name ?: config('app.name')),
                    scheduleName: $schedule->name,
                    reportLabel: ReportCatalog::label($schedule->report_key),
                    periodLabel: $file['period_label'],
                    customSubject: $schedule->subject,
                    customMessage: $schedule->message,
                    fileName: $file['filename'],
                    fileContent: $tooLarge ? null : $file['content'],
                    mime: $file['mime'],
                    fileSize: strlen($file['content']),
                ));
            } catch (Throwable $e) {
                report($e);
                $failed[$email] = Str::limit($e->getMessage(), 120);
            }
        }

        return $failed;
    }

    private function pause(ReportSchedule $schedule, string $reason): void
    {
        $schedule->forceFill([
            'is_active' => false,
            'paused_reason' => Str::limit($reason, 250),
            'next_run_at' => null,
        ])->save();
    }

    private function prune(ReportSchedule $schedule): void
    {
        $keepFrom = ReportScheduleRun::query()
            ->where('report_schedule_id', $schedule->id)
            ->orderByDesc('id')
            ->skip(ReportSchedule::RUNS_KEPT - 1)
            ->value('id');
        if ($keepFrom) {
            ReportScheduleRun::query()
                ->where('report_schedule_id', $schedule->id)
                ->where('id', '<', $keepFrom)
                ->delete();
        }
    }
}
