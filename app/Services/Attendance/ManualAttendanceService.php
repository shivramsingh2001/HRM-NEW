<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Services\AttendanceSummaryService;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Feature A — admin/HR/manager marking a day's attendance by hand, for any
 * status (present / absent / half day / on leave / first- or second-half leave).
 */
class ManualAttendanceService
{
    public function __construct(
        private AttendanceCalculator $calc,
        private LatePolicyService $latePolicy,
        private AttendanceSummaryService $summary,
        private LeaveService $leaveService,
    ) {
    }

    /**
     * @param array{
     *   user_id:int, tenant_id:int, date:string, status:AttendanceStatus,
     *   clock_in?:?string, clock_out?:?string, remarks?:?string, leave_type_id?:?int
     * } $input
     *
     * @return array{attendance: Attendance, leave: ?\App\Models\Leave, is_update: bool, shift: ?object}
     */
    public function mark(array $input, User $actor): array
    {
        return DB::transaction(function () use ($input, $actor) {
            /** @var AttendanceStatus $status */
            $status = $input['status'];
            $userId = (int) $input['user_id'];
            $tenantId = (int) $input['tenant_id'];
            $date = Carbon::parse($input['date'])->format('Y-m-d');

            $shift = $this->resolveShift($userId, $tenantId, $date);
            $jobDetail = UserJobDetail::where('user_id', $userId)->where('tenant_id', $tenantId)->first();

            $attrs = [
                'attendance_status' => $status->value,
                'attendance_type' => 'manual',
                'marked_by' => $actor->id,
                'is_regularized' => 0,
                'status' => 1,
                'day_fraction' => $status->dayFraction(),
                'effective_status' => null, // LatePolicyService recomputes below
                'policy_note' => null,
                'shift_id' => $shift->id ?? null,
                'scheduled_shift_start' => $shift->start_time ?? null,
                'scheduled_shift_end' => $shift->end_time ?? null,
                'branch_id' => $jobDetail->office_branch ?? null,
                'clock_in' => null,
                'clock_out' => null,
                'clock_in_address' => 'Marked by ' . $actor->name,
                'clock_out_address' => null,
                'worked_hours' => 0,
                'total_hours' => null,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
                'overtime_minutes' => 0,
                'remarks' => $input['remarks'] ?? ('Marked by ' . $actor->name . ' (' . $status->value . ')'),
            ];

            if ($status->requiresClockTimes()) {
                $clockIn = Carbon::parse($date . ' ' . $input['clock_in']);
                $clockOut = $this->calc->resolveClockOut(
                    $clockIn,
                    Carbon::parse($date . ' ' . $input['clock_out']),
                    $shift
                );
                $seconds = $this->calc->workedSeconds($clockIn, $clockOut);

                $attrs['clock_in'] = $clockIn->format('Y-m-d H:i:s');
                $attrs['clock_out'] = $clockOut->format('Y-m-d H:i:s');
                $attrs['clock_out_address'] = 'Marked by ' . $actor->name;
                $attrs['worked_hours'] = $this->calc->decimalHours($seconds);
                $attrs['total_hours'] = $this->calc->formatDuration($seconds);

                if ($shift) {
                    $attrs['late_minutes'] = $this->calc->lateMinutes(
                        $shift,
                        Carbon::parse($date . ' ' . $shift->start_time),
                        $clockIn
                    );
                }
            }

            $attendance = Attendance::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $userId, 'date' => $date],
                $attrs
            );
            $isUpdate = !$attendance->wasRecentlyCreated;

            AttendanceLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'attendance_id' => $attendance->id,
                'event_type' => 'manual_adjustment',
                'event_time' => now(),
                'verification_method' => 'system',
                'user_agent' => 'ManualAttendanceService',
                'raw_data' => json_encode([
                    'status' => $status->value,
                    'is_update' => $isUpdate,
                    'marked_by' => $actor->id,
                    'marked_by_name' => $actor->name,
                ]),
            ]);

            DB::table('user_shifts')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('date', $date)
                ->update(['status' => 'complete', 'updated_at' => now()]);

            // Leave marking -> real approved leave + ledger.
            $leave = null;
            if ($status->isLeaveKind() && !empty($input['leave_type_id'])) {
                $leave = $this->leaveService->createApproved([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'leave_type_id' => (int) $input['leave_type_id'],
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_session' => $status->leaveSession(),
                    'end_session' => $status->leaveSession(),
                    'leave_count' => $status === AttendanceStatus::OnLeave ? 1.0 : 0.5,
                    'reason' => $input['remarks'] ?? 'Marked from attendance',
                    'applied_by' => $actor->id,
                    'deduct_balance' => true,
                ]);
            }

            $ym = Carbon::parse($date)->format('Y-m');
            $this->latePolicy->recalculateMonth($userId, $tenantId, $ym);
            $this->summary->updateMonthlySummary($userId, $ym, $tenantId);

            return [
                'attendance' => $attendance->fresh(),
                'leave' => $leave,
                'is_update' => $isUpdate,
                'shift' => $shift,
            ];
        });
    }

    /**
     * Resolve the user's assigned shift for the date (user_shifts -> shifts),
     * scoped to the tenant. Null when none.
     */
    private function resolveShift(int $userId, int $tenantId, string $date): ?object
    {
        $shiftId = DB::table('user_shifts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->value('shift_id');

        if (!$shiftId) {
            return null;
        }

        return Shift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($shiftId);
    }
}
