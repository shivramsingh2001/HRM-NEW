<?php

namespace App\Services\Attendance;

use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Company Policies → Overtime → "Automatic overtime calculation".
 *
 * For one employee-day: overtime = last clock-out − (end of the LAST shift of
 * the day + start offset), where the offset is the shift's grace minutes or a
 * fixed number of minutes. 2nd+ shifts are already paid as overtime through
 * ExtraShiftOvertime, so counting after the last shift never pays twice.
 * Overnight shifts use the real next-day end (TenantShiftResolver).
 *
 * The result is stored as ONE approved overtime_requests row (source = auto),
 * so approvals, reports, Employee 360 and payroll keep reading one table.
 * No clock-out, a system auto clock-out, an early clock-out or no scheduled
 * shift = no overtime. Minimum / per-day / monthly limits apply. A row HR has
 * adjusted or rejected, a requested (non-auto) row, a locked month and a month
 * whose payslip is processed or paid are never touched.
 */
class AutoOvertimeService
{
    public function __construct(
        private OvertimePolicyService $policy,
        private TenantShiftResolver $shifts,
    ) {
    }

    /**
     * Raw measurement for a day under the company's start rule.
     *
     * @return array{minutes:int, attendance_id:?int, shift_end:?Carbon, starts_at:?Carbon, clock_out:?Carbon, reason:?string}
     */
    public function measure(int $tenantId, int $userId, string $date): array
    {
        $date = Carbon::parse($date)->toDateString();
        $out = ['minutes' => 0, 'attendance_id' => null, 'shift_end' => null, 'starts_at' => null, 'clock_out' => null, 'reason' => null];

        $att = DB::table('attendances')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereDate('date', $date)->first(['id', 'clock_out', 'remarks']);
        if (! $att) {
            return ['reason' => 'No attendance'] + $out;
        }
        $out['attendance_id'] = (int) $att->id;
        if (empty($att->clock_out)) {
            return ['reason' => 'No clock-out'] + $out;
        }
        if ($this->isSystemClockOut($tenantId, $userId, $date, (string) $att->remarks)) {
            return ['reason' => 'Clocked out automatically by the system'] + $out;
        }

        $instances = $this->shifts->instancesForUserDate($userId, $tenantId, $date);
        if ($instances->isEmpty()) {
            return ['reason' => 'No scheduled shift'] + $out;
        }
        $last = $instances->sortBy(fn ($i) => $i['end']->getTimestamp())->last();

        $company = $this->policy->company($tenantId);
        $offset = ($company->auto_start_basis ?? 'grace') === 'fixed'
            ? (int) ($company->auto_start_after_minutes ?? 0)
            : (int) ($last['shift']->grace_minutes ?? 0);

        $out['shift_end'] = $last['end']->copy();
        $out['starts_at'] = $last['end']->copy()->addMinutes($offset);
        $out['clock_out'] = Carbon::parse($att->clock_out);

        if ($out['clock_out']->lte($out['starts_at'])) {
            return ['reason' => 'Left before overtime starts'] + $out;
        }
        $out['minutes'] = (int) floor(($out['clock_out']->getTimestamp() - $out['starts_at']->getTimestamp()) / 60);

        return $out;
    }

    /**
     * Create / update / remove the automatic overtime row for one day.
     * Returns the row (null when there is none). No-op outside automatic mode.
     */
    public function syncDay(int $userId, int $tenantId, string $date): ?OvertimeRequest
    {
        $date = Carbon::parse($date)->toDateString();
        if (! $this->policy->isAuto($tenantId) || $this->frozen($tenantId, $userId, $date)) {
            return null;
        }

        $existing = $this->policy->existing($userId, $date);
        if ($existing && ($existing->source !== OvertimeRequest::SOURCE_AUTO || $existing->manually_adjusted_at)) {
            return $existing; // a request / HR decision wins over the calculation
        }

        $m = $this->measure($tenantId, $userId, $date);
        $hours = $this->allowedHours($tenantId, $userId, $date, round($m['minutes'] / 60, 2), $existing?->id);

        if ($hours <= 0) {
            $existing?->delete();

            return null;
        }

        $attributes = [
            'source' => OvertimeRequest::SOURCE_AUTO,
            'status' => 'approved',
            'overtime_hours' => $hours,
            'approved_hours' => $hours,
            'approved_by' => null,
            'approved_at' => $existing?->approved_at ?? now(),
            'attendance_id' => $m['attendance_id'],
            'auto_minutes' => $m['minutes'],
            'reason' => 'Calculated from attendance: clock-out ' . $m['clock_out']->format('d M H:i')
                . ', overtime from ' . $m['starts_at']->format('d M H:i')
                . ' (shift ended ' . $m['shift_end']->format('H:i') . ')',
        ];

        if ($existing) {
            $existing->fill($attributes)->save();

            return $existing;
        }

        return OvertimeRequest::create(['tenant_id' => $tenantId, 'user_id' => $userId, 'date' => $date] + $attributes);
    }

    /**
     * Recalculate every day of $ym (Y-m) for the tenant — or for $userIds.
     * Returns the number of days that now carry automatic overtime.
     */
    public function syncMonth(int $tenantId, string $ym, ?array $userIds = null): int
    {
        if (! $this->policy->isAuto($tenantId)) {
            return 0;
        }
        $from = Carbon::createFromFormat('Y-m-d', $ym . '-01')->startOfMonth();

        return $this->syncRange($tenantId, $from, $from->copy()->endOfMonth(), $userIds);
    }

    public function syncRange(int $tenantId, Carbon $from, Carbon $to, ?array $userIds = null): int
    {
        if (! $this->policy->isAuto($tenantId)) {
            return 0;
        }
        $range = [$from->toDateString(), $to->toDateString()];

        // Days with a clock-out, plus days that already have an automatic row (to clean up).
        $days = DB::table('attendances')->where('tenant_id', $tenantId)->whereBetween('date', $range)
            ->whereNotNull('clock_out')
            ->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
            ->get(['user_id', 'date'])
            ->merge(DB::table('overtime_requests')->where('tenant_id', $tenantId)->whereBetween('date', $range)
                ->where('source', OvertimeRequest::SOURCE_AUTO)
                ->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
                ->get(['user_id', 'date']))
            ->map(fn ($r) => $r->user_id . '|' . Carbon::parse($r->date)->toDateString())
            ->unique()->sort()->values();

        $count = 0;
        foreach ($days as $key) {
            [$userId, $date] = explode('|', $key);
            if ($this->syncDay((int) $userId, $tenantId, $date)?->source === OvertimeRequest::SOURCE_AUTO) {
                $count++;
            }
        }

        return $count;
    }

    /** Hours after the employee's eligibility, minimum, per-day and monthly limits. */
    private function allowedHours(int $tenantId, int $userId, string $date, float $hours, ?int $ignoreId): float
    {
        $s = $this->policy->forEmployee($tenantId, $userId);
        if (! $s->eligible || $hours <= 0) {
            return 0.0;
        }
        $min = (float) ($s->min_hours ?? 0);
        if ($min > 0 && $hours < $min) {
            return 0.0;
        }
        $maxDay = (float) ($s->max_hours_per_day ?? 0);
        if ($maxDay > 0) {
            $hours = min($hours, $maxDay);
        }
        $cap = $this->policy->monthlyCap($tenantId, $userId);
        if ($cap !== null) {
            $hours = min($hours, max(0.0, $cap - $this->policy->bookedHours($tenantId, $userId, $date, $ignoreId)));
            if ($min > 0 && $hours < $min) {
                return 0.0;
            }
        }

        return round($hours, 2);
    }

    /** Locked month, or this employee's payslip for the month is already processed / paid. */
    private function frozen(int $tenantId, int $userId, string $date): bool
    {
        $ym = substr($date, 0, 7);
        if (app(PeriodLockService::class)->isLocked($tenantId, $ym)) {
            return true;
        }

        return DB::table('monthly_payrolls')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('payroll_month', $ym)->whereIn('payment_status', ['processed', 'paid'])->exists();
    }

    /** attendance:auto-clockout closed this day — not a real clock-out. */
    private function isSystemClockOut(int $tenantId, int $userId, string $date, string $remarks): bool
    {
        if (str_contains($remarks, 'Auto clock-out')) {
            return true;
        }
        $lastOut = DB::table('attendance_punches')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereDate('date', $date)->where('direction', 'out')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'void'))
            ->orderByDesc('punched_at')->first(['method', 'source']);

        return $lastOut && ($lastOut->method === 'auto_clockout');
    }
}
