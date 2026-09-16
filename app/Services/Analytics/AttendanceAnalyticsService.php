<?php

namespace App\Services\Analytics;

use App\Services\AttendanceSummaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-F — tenant-facing attendance analytics, read off the summary +
 * raw layers with short-lived caching (never a per-request full recompute).
 */
class AttendanceAnalyticsService
{
    public function __construct(private AttendanceSummaryService $summaries)
    {
    }

    /** Employees currently clocked in (open row today, within a sane cap). */
    public function presentNow(int $tenantId): array
    {
        return Cache::remember("analytics:presentnow:{$tenantId}", 60, function () use ($tenantId) {
            $today = now()->toDateString();
            $rows = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->where('date', $today)
                ->whereNotNull('clock_in')
                ->where(function ($q) {
                    $q->whereNull('clock_out')->orWhere('clock_out', '')->orWhere('clock_out', '0000-00-00 00:00:00');
                })
                ->count();

            $expected = DB::table('users')->where('tenant_id', $tenantId)->where('status', 1)->count();

            return ['present_now' => $rows, 'active_headcount' => $expected, 'as_of' => now()->toIso8601String()];
        });
    }

    /**
     * Daily series between two dates for the tenant (or one department).
     *
     * @return array<int,array<string,mixed>>
     */
    public function trends(int $tenantId, string $from, string $to, ?int $departmentId = null): array
    {
        $key = "analytics:trends:{$tenantId}:{$from}:{$to}:" . ($departmentId ?? 'all');

        return Cache::remember($key, 600, function () use ($tenantId, $from, $to, $departmentId) {
            $userIds = DB::table('users as u')
                ->when($departmentId, fn ($q) => $q->join('user_job_details as jd', 'jd.user_id', '=', 'u.id')
                    ->where('jd.department', $departmentId))
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->pluck('u.id');

            $rows = DB::table('attendances')
                ->select('date',
                    DB::raw('COUNT(*) as rows_count'),
                    DB::raw("SUM(CASE WHEN effective_status IN ('present','late','overtime','early_departure') OR attendance_status IN ('present','late','overtime') THEN 1 ELSE 0 END) as present"),
                    DB::raw("SUM(CASE WHEN effective_status='half_day' OR attendance_status='half_day' THEN 1 ELSE 0 END) as half_day"),
                    DB::raw("SUM(CASE WHEN attendance_status IN ('on_leave','first_half_leave','second_half_leave') THEN 1 ELSE 0 END) as on_leave"),
                    DB::raw('SUM(COALESCE(late_minutes,0)) as late_minutes'),
                    DB::raw('SUM(COALESCE(worked_hours,0)) as worked_hours'))
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $userIds)
                ->whereBetween('date', [$from, $to])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $headcount = max(1, $userIds->count());

            return $rows->map(fn ($r) => [
                'date' => (string) $r->date,
                'present' => (int) $r->present,
                'half_day' => (int) $r->half_day,
                'on_leave' => (int) $r->on_leave,
                'absent_est' => max(0, $headcount - (int) $r->present - (int) $r->half_day - (int) $r->on_leave),
                'late_minutes' => (int) $r->late_minutes,
                'worked_hours' => round((float) $r->worked_hours, 2),
                'absence_rate' => round(1 - ((int) $r->present + 0.5 * (int) $r->half_day) / $headcount, 4),
            ])->all();
        });
    }

    /** Overtime cost for a month = Σ overtime_hours × hourly_rate × multiplier. */
    public function overtimeCost(int $tenantId, string $yearMonth): array
    {
        $users = DB::table('users')->where('tenant_id', $tenantId)->where('status', 1)->pluck('id');
        $hours = 0.0;
        foreach ($users as $uid) {
            $m = $this->summaries->getMonthly((int) $uid, $yearMonth, $tenantId, allowStale: true);
            $hours += (float) ($m['total_overtime_hours'] ?? 0);
        }

        $dynamicEnabled = (bool) DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled');

        if ($dynamicEnabled) {
            // No column stores an explicit hourly rate for a dynamic
            // structure — approximate from CTC assuming a standard
            // 26-day/8-hour month (the same default this codebase already
            // uses for payroll_calculation_type='hour_based' templates).
            // This is an estimate for the analytics widget, not an
            // authoritative payroll figure.
            $avgCtc = (float) (DB::table('payroll_employee_structures')
                ->where('tenant_id', $tenantId)
                ->where('is_current', 1)
                ->avg('ctc') ?? 0);
            $rate = $avgCtc > 0 ? round(($avgCtc / 12) / (26 * 8), 2) : 0.0;
        } else {
            // user_payrolls itself has no hourly_rate column — that lives on
            // the linked payroll_masters template (hourly_rate_applied).
            $rate = (float) (DB::table('user_payrolls')
                ->join('payroll_masters', 'user_payrolls.payroll_master_id', '=', 'payroll_masters.id')
                ->where('user_payrolls.tenant_id', $tenantId)
                ->where('user_payrolls.is_current', 1)
                ->avg('payroll_masters.hourly_rate_applied') ?? 0);
        }

        $policy = app(\App\Services\Attendance\PolicyResolver::class)->forTenantMonth($tenantId, $yearMonth);

        return [
            'year_month' => $yearMonth,
            'overtime_hours' => round($hours, 2),
            'avg_hourly_rate' => round($rate, 2),
            'multiplier' => $policy->overtimeMultiplier,
            'estimated_cost' => round($hours * $rate * $policy->overtimeMultiplier, 2),
        ];
    }
}
