<?php

namespace App\Services\Shift;

use App\Services\Attendance\PeriodLockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "May this employee's shift on this day still be changed?" — shared by the
 * roster day edit, direct swaps / changes and employee shift requests.
 *
 * Always refused: locked attendance month, payslip for the month already
 * processed / paid, employee not active. Refused unless $allowWithAttendance:
 * the employee already clocked in that day (requests and swaps never allow
 * it; an admin edit may, with a reason — attendance is recalculated after).
 */
class ShiftChangeGuard
{
    public function __construct(private PeriodLockService $locks)
    {
    }

    public function dateError(int $tenantId, int $userId, string $date, bool $allowWithAttendance = false, ?string $name = null): ?string
    {
        $who = $name ?: 'This employee';
        $day = Carbon::parse($date)->format('d M Y');
        $ym = substr($date, 0, 7);

        if ($this->locks->isLocked($tenantId, $ym)) {
            return "Attendance for " . Carbon::parse($date)->format('F Y') . " is locked — shifts on {$day} can't be changed.";
        }

        $paid = DB::table('monthly_payrolls')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('payroll_month', $ym)->whereIn('payment_status', ['processed', 'paid'])->exists();
        if ($paid) {
            return "{$who}'s payslip for " . Carbon::parse($date)->format('F Y') . " is already processed — the shift on {$day} can't be changed.";
        }

        $active = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->where('status', 1)->exists();
        if (! $active) {
            return "{$who} is not an active employee.";
        }

        if (! $allowWithAttendance && $this->hasAttendance($tenantId, $userId, $date)) {
            return "{$who} already has attendance on {$day} — that day's shift can't be changed.";
        }

        return null;
    }

    public function hasAttendance(int $tenantId, int $userId, string $date): bool
    {
        return DB::table('attendances')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('date', $date)->whereNotNull('clock_in')->exists();
    }
}
