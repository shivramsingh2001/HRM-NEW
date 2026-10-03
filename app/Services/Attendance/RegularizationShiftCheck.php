<?php

namespace App\Services\Attendance;

use App\Support\ShiftWindow;
use Illuminate\Support\Facades\DB;

/**
 * Shared validation for a regularization request's shift + times (web store
 * and update, mobile API store). Multi-shift: the request may name which of
 * the day's shifts it corrects (user_shift_id, null = the primary shift).
 * Overnight: for a night shift the out time is on the next day, so an out
 * time earlier than the in time is valid — for any other shift it is not.
 */
class RegularizationShiftCheck
{
    public function __construct(private TenantShiftResolver $shifts)
    {
    }

    /**
     * @return array{user_shift_id: ?int, field: ?string, error: ?string}
     */
    public function check(int $userId, int $tenantId, string $date, $userShiftId, ?string $inTime, ?string $outTime): array
    {
        $userShiftId = filled($userShiftId) ? (int) $userShiftId : null;
        $shift = null;

        if ($userShiftId) {
            $row = DB::table('user_shifts')
                ->where('id', $userShiftId)
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereDate('date', $date)
                ->first(['shift_id']);

            if (!$row) {
                return ['user_shift_id' => null, 'field' => 'user_shift_id', 'error' => 'Choose one of your shifts on this date.'];
            }

            $shift = DB::table('shifts')->where('id', $row->shift_id)->first();
        } else {
            $shift = $this->shifts->forUserDate($userId, $tenantId, $date);
        }

        if (filled($inTime) && filled($outTime)
            && ShiftWindow::toMinutes($outTime) <= ShiftWindow::toMinutes($inTime)
            && !($shift && ShiftWindow::isOvernight($shift))) {
            return ['user_shift_id' => $userShiftId, 'field' => 'out_time', 'error' => 'Out time must be after in time'];
        }

        return ['user_shift_id' => $userShiftId, 'field' => null, 'error' => null];
    }
}
