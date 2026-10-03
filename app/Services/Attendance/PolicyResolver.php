<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the attendance policy in force for a tenant on a given date.
 *
 * Precedence:
 *   1. newest attendance_policies row for the tenant with effective_from <= date
 *   2. the global default row (tenant_id IS NULL)
 *   3. a legacy dual-read of tenants.late_halfday_enabled / monthly_late_allowance
 *      layered on top of #2 (until those columns are dropped)
 *   4. AttendancePolicySnapshot::default() (config only) if the table is empty
 *
 * Results are memoised per (tenant, effective_from month) for the request and
 * cached across requests; bust with forget() after a settings save.
 */
class PolicyResolver
{
    /** @var array<string,AttendancePolicySnapshot> */
    private array $memo = [];

    public function forTenantDate(int $tenantId, string $date): AttendancePolicySnapshot
    {
        $day = Carbon::parse($date)->format('Y-m-d');
        $key = $tenantId . '|' . $day;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->resolve($tenantId, $day);
    }

    /**
     * Policy in force on the 1st of the given month — the anchor a monthly
     * recalculation (LatePolicyService / summaries) must use so a later-dated
     * policy row never retroactively re-grades a closed month.
     */
    public function forTenantMonth(int $tenantId, string $yearMonth): AttendancePolicySnapshot
    {
        return $this->forTenantDate($tenantId, Carbon::parse($yearMonth . '-01')->format('Y-m-d'));
    }

    /**
     * The policy ONE employee follows on a date: the company policy with that
     * employee's custom values (Employee 360 → Policies) on top. Identical to
     * forTenantDate() for an employee with no custom values. Use this wherever
     * a single employee's day / month is being graded.
     */
    public function forUserDate(int $tenantId, int $userId, string $date): AttendancePolicySnapshot
    {
        $company = $this->forTenantDate($tenantId, $date);
        $custom = app(\App\Services\EmployeePolicyService::class)->section($tenantId, $userId, 'attendance');

        return $custom ? $company->withOverrides($custom) : $company;
    }

    /** forUserDate() anchored to the 1st of the month — see forTenantMonth(). */
    public function forUserMonth(int $tenantId, int $userId, string $yearMonth): AttendancePolicySnapshot
    {
        return $this->forUserDate($tenantId, $userId, Carbon::parse($yearMonth . '-01')->format('Y-m-d'));
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    private function resolve(int $tenantId, string $day): AttendancePolicySnapshot
    {
        if (! $this->tableReady()) {
            return $this->withLegacyOverlay($tenantId, AttendancePolicySnapshot::default());
        }

        $row = DB::table('attendance_policies')
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->whereDate('effective_from', '<=', $day)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($row) {
            return AttendancePolicySnapshot::fromRow($row);
        }

        $global = DB::table('attendance_policies')
            ->whereNull('deleted_at')
            ->whereNull('tenant_id')
            ->whereDate('effective_from', '<=', $day)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        $base = $global
            ? AttendancePolicySnapshot::fromRow($global)
            : AttendancePolicySnapshot::default();

        return $this->withLegacyOverlay($tenantId, $base);
    }

    /**
     * Until the tenants.* late columns are dropped, a tenant that set them but
     * has no policy row keeps its configured late behaviour.
     */
    private function withLegacyOverlay(int $tenantId, AttendancePolicySnapshot $base): AttendancePolicySnapshot
    {
        $t = DB::table('tenants')
            ->where('id', $tenantId)
            ->first(['late_halfday_enabled', 'monthly_late_allowance']);

        if (! $t) {
            return $base;
        }

        $enabled = $t->late_halfday_enabled !== null
            ? (bool) $t->late_halfday_enabled
            : $base->lateHalfdayEnabled;
        $allowance = $t->monthly_late_allowance !== null
            ? (int) $t->monthly_late_allowance
            : $base->monthlyLateAllowance;

        if ($enabled === $base->lateHalfdayEnabled && $allowance === $base->monthlyLateAllowance) {
            return $base;
        }

        return new AttendancePolicySnapshot(
            tenantId: $tenantId,
            effectiveFrom: $base->effectiveFrom,
            presentRatio: $base->presentRatio,
            halfDayRatio: $base->halfDayRatio,
            fallbackPresentHours: $base->fallbackPresentHours,
            fallbackHalfHours: $base->fallbackHalfHours,
            fullDayMinHours: $base->fullDayMinHours,
            overtimeAfterHours: $base->overtimeAfterHours,
            overtimeMultiplier: $base->overtimeMultiplier,
            graceMinutes: $base->graceMinutes,
            roundingMinutes: $base->roundingMinutes,
            lateHalfdayEnabled: $enabled,
            monthlyLateAllowance: $allowance,
            minRestHours: $base->minRestHours,
            maxDailyHours: $base->maxDailyHours,
            sandwichLeave: $base->sandwichLeave,
            graceMode: $base->graceMode,
            fixedGraceMinutes: $base->fixedGraceMinutes,
            lateAttendanceAction: $base->lateAttendanceAction,
            lateDeductionEnabled: $base->lateDeductionEnabled,
            lateDeductionMultiplier: $base->lateDeductionMultiplier,
            lateDeductionMode: $base->lateDeductionMode,
            lateDeductionAmount: $base->lateDeductionAmount,
            monthlyEarlyAllowance: $base->monthlyEarlyAllowance,
            earlyAttendanceAction: $base->earlyAttendanceAction,
            earlyDeductionEnabled: $base->earlyDeductionEnabled,
            earlyDeductionMultiplier: $base->earlyDeductionMultiplier,
            earlyDeductionMode: $base->earlyDeductionMode,
            earlyDeductionAmount: $base->earlyDeductionAmount,
            dayClassificationEnabled: $base->dayClassificationEnabled,
        );
    }

    private function tableReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = DB::getSchemaBuilder()->hasTable('attendance_policies');
            } catch (\Throwable $e) {
                $ready = false;
            }
        }

        return $ready;
    }
}
