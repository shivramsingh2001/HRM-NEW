<?php

namespace App\Services\Reports;

use App\Models\CompanyBranch;
use App\Models\Department;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\FeatureService;
use App\Support\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reports → Scheduled Reports: create / edit / pause / delete schedules and
 * queue "Send now". Sending itself is ReportScheduleRunner (CLI).
 */
class ReportScheduleService
{
    /** @return Collection<int, ReportSchedule> */
    public function list(int $tenantId): Collection
    {
        return ReportSchedule::query()
            ->where('tenant_id', $tenantId)
            ->with('owner:id,name')
            ->orderByDesc('is_active')->orderBy('name')
            ->get();
    }

    /** @return array{total: int, active: int, paused: int, failed: int} */
    public function summary(Collection $schedules): array
    {
        return [
            'total' => $schedules->count(),
            'active' => $schedules->where('is_active', true)->count(),
            'paused' => $schedules->where('is_active', false)->count(),
            'failed' => $schedules->where('last_status', 'failed')->count(),
        ];
    }

    /**
     * Options for the add/edit drawer.
     *
     * @return array{reports: array<string, mixed>, branches: \Illuminate\Support\Collection<int, mixed>, departments: \Illuminate\Support\Collection<int, mixed>}
     */
    public function formOptions(User $user): array
    {
        $branchesOn = app(FeatureService::class)->enabledForCurrentTenant('branches');

        return [
            'reports' => ReportCatalog::availableFor($user),
            'branches' => $branchesOn
                ? CompanyBranch::query()->where('tenant_id', $user->tenant_id)->where('status', 1)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'departments' => Department::query()->where('tenant_id', $user->tenant_id)->where('status', 1)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Create or update. Saving makes the saver the owner (the report runs as
     * them) and re-activates a paused schedule — editing is how one is fixed.
     *
     * @param  array<string, mixed>  $data  validated ReportScheduleRequest data
     */
    public function save(?ReportSchedule $schedule, array $data, User $actor): ReportSchedule
    {
        $schedule ??= new ReportSchedule(['tenant_id' => $actor->tenant_id]);
        $periods = ReportCatalog::periodsFor($data['report_key']);

        $schedule->fill([
            'created_by' => $actor->id,
            'name' => $data['name'],
            'report_key' => $data['report_key'],
            'period' => in_array($data['period'] ?? null, $periods, true) ? $data['period'] : ($periods[0] ?? null),
            'filters' => array_filter([
                'branch_id' => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
                'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
            ]) ?: null,
            'frequency' => $data['frequency'],
            'weekdays' => match ($data['frequency']) {
                'daily' => $this->weekdays($data['weekdays'] ?? []),
                'weekly' => [(int) ($data['weekly_day'] ?? 1)],
                default => null,
            },
            'day_of_month' => $data['frequency'] === 'monthly' ? (int) $data['day_of_month'] : null,
            'send_time' => $data['send_time'],
            'recipients' => $data['recipients'],
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'] ?? null,
            'is_active' => true,
            'paused_reason' => null,
        ]);
        $schedule->next_run_at = ReportScheduleTiming::nextRunAfter($schedule, CarbonImmutable::now());
        $schedule->save();

        return $schedule;
    }

    public function setActive(ReportSchedule $schedule, bool $active): ReportSchedule
    {
        $schedule->forceFill([
            'is_active' => $active,
            'paused_reason' => null,
            'next_run_at' => $active ? ReportScheduleTiming::nextRunAfter($schedule, CarbonImmutable::now()) : null,
        ])->save();

        return $schedule;
    }

    /** Picked up by `reports:send-scheduled` within a minute. */
    public function requestSendNow(ReportSchedule $schedule): void
    {
        $schedule->forceFill(['run_requested_at' => now()])->save();
    }

    public function delete(ReportSchedule $schedule): void
    {
        $schedule->delete(); // runs go with it (FK cascade)
    }

    /**
     * @param  array<int, mixed>  $days
     * @return array<int, int>|null null = every day
     */
    private function weekdays(array $days): ?array
    {
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        return ($days === [] || count($days) === 7) ? null : $days;
    }
}
