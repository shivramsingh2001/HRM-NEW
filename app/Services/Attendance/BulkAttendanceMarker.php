<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Exceptions\PeriodLockedException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Marks the same attendance status for several employees at once (Team Members →
 * select → Mark attendance). Each employee goes through the one-employee path,
 * AttendanceEntryService::markStatus(), in its own transaction — so punches are
 * voided, leave is created, the audit log is written exactly as for a single mark,
 * and one employee's failure (locked month, no shift…) never undoes the others.
 */
class BulkAttendanceMarker
{
    public function __construct(
        private AttendanceEntryService $entry,
        private TenantShiftResolver $shifts,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @param  array{date:string, end_date?:?string, status:AttendanceStatus, use_shift_times?:bool,
     *               clock_in?:?string, clock_out?:?string, clock_out_next_day?:bool,
     *               leave_type_id?:?int, remarks?:?string}  $input
     * @return array{marked: list<array{user_id:int, name:string, days:int}>, failed: list<array{user_id:int, name:string, reason:string}>}
     */
    public function mark(array $userIds, array $input, User $actor): array
    {
        $tenantId = (int) $actor->tenant_id;
        $names = User::where('tenant_id', $tenantId)->whereIn('id', $userIds)->pluck('name', 'id');
        $marked = [];
        $failed = [];

        foreach ($userIds as $userId) {
            $name = (string) ($names[$userId] ?? ('#'.$userId));

            try {
                $days = DB::transaction(fn () => ! empty($input['use_shift_times'])
                    ? $this->markWithShiftTimes($userId, $tenantId, $input, $actor)
                    : $this->markOne($userId, $tenantId, $input, $input['date'], $input['end_date'] ?? null, $actor));
                $marked[] = ['user_id' => $userId, 'name' => $name, 'days' => $days];
            } catch (BulkMarkSkipped|PeriodLockedException $e) {
                $failed[] = ['user_id' => $userId, 'name' => $name, 'reason' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);
                $failed[] = ['user_id' => $userId, 'name' => $name, 'reason' => 'Could not be marked (unexpected error).'];
            }
        }

        return ['marked' => $marked, 'failed' => $failed];
    }

    private function markOne(int $userId, int $tenantId, array $input, string $date, ?string $endDate, User $actor, ?array $times = null): int
    {
        $result = $this->entry->markStatus([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'date' => $date,
            'end_date' => $endDate,
            'status' => $input['status'],
            'clock_in' => $times['in'] ?? ($input['clock_in'] ?? null),
            'clock_out' => $times['out'] ?? ($input['clock_out'] ?? null),
            'clock_out_next_day' => $times['next_day'] ?? ! empty($input['clock_out_next_day']),
            'leave_type_id' => $input['leave_type_id'] ?? null,
            'remarks' => $input['remarks'] ?? null,
        ], $actor);

        return $result['marked'];
    }

    /**
     * Present with each employee's own shift start / end, day by day (the shift
     * can differ per day). A day without a shift — or that is still running —
     * stops this employee with a clear reason; earlier days of the same employee
     * are rolled back with it (one transaction per employee).
     */
    private function markWithShiftTimes(int $userId, int $tenantId, array $input, User $actor): int
    {
        $start = Carbon::parse($input['date']);
        $end = ! empty($input['end_date']) ? Carbon::parse($input['end_date']) : $start->copy();
        $days = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $shift = $this->shifts->forUserDate($userId, $tenantId, $date);
            if (! $shift || empty($shift->start_time) || empty($shift->end_time)) {
                throw new BulkMarkSkipped('No shift assigned on '.$d->format('d M Y').'.');
            }

            $in = substr((string) $shift->start_time, 0, 5);
            $out = substr((string) $shift->end_time, 0, 5);
            $overnight = $out <= $in;
            $shiftEnd = Carbon::parse(($overnight ? $d->copy()->addDay()->toDateString() : $date).' '.$out);
            if ($shiftEnd->gt(now())) {
                throw new BulkMarkSkipped('Shift on '.$d->format('d M Y').' has not ended yet (ends '.$shiftEnd->format('d M h:i A').').');
            }

            $days += $this->markOne($userId, $tenantId, $input, $date, null, $actor, ['in' => $in, 'out' => $out, 'next_day' => false]);
        }

        return $days;
    }
}
