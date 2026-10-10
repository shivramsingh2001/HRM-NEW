<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use App\Models\UserWeekoffs;
use App\Services\RbacService;
use App\Support\ShiftWindow;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Day status + team list helpers of the Team pages (Team list, member profile,
 * Monthly Attendance Summary). Moved out of TeamController unchanged (code-quality
 * plan, Phase 3). NOTE: the attendance reports have their own, slightly different
 * copy (AttendanceReportHelpers) — see docs.
 */
trait TeamAttendanceStatus
{
    /**
     * Calculate attendance status based on shift timings from attendance record
     *
     * @param  float  $totalHours
     * @param  int  $userId
     * @param  string  $date
     * @param  object|null  $attendance
     * @return string
     */
    private function getAttendanceStatusByShift($totalHours, $userId, $date, $attendance = null)
    {
        // A hand-set (admin/HR/manager) or policy-resolved status is
        // authoritative — do not recompute it from worked hours.
        if ($attendance) {
            $persisted = $this->persistedDayStatus($attendance);
            if ($persisted !== null) {
                return $persisted;
            }
        }

        // Scheduled shift length from the attendance record (0 => the policy's
        // absolute-hours fallback).
        $expectedSeconds = 0;
        if ($attendance && $attendance->scheduled_shift_start && $attendance->scheduled_shift_end) {
            // Overnight shifts (e.g. 22:00 to 06:00) end on the next day.
            $expectedSeconds = ShiftWindow::spanMinutes($attendance->scheduled_shift_start, $attendance->scheduled_shift_end) * 60;
        }

        // The tenant's attendance policy (ratios + Day Classification switch),
        // same as Attendance summary and Payroll.
        $policy = app(\App\Services\Attendance\PolicyResolver::class)
            ->forUserDate((int) Auth::user()->tenant_id, (int) $userId, Carbon::parse($date)->format('Y-m-d'));

        return match ($policy->classify((float) ($totalHours ?? 0), $expectedSeconds)) {
            'present' => 'Present',
            'half_day' => 'halfday',
            default => 'Absent',
        };
    }

    /**
     * Display status for a row whose status was set by hand or resolved by the
     * late-allowance policy. Returns null when neither applies (fall through to
     * the hours-based calculation).
     */
    private function persistedDayStatus($attendance): ?string
    {
        $isManual = (($attendance->attendance_type ?? null) === 'manual')
            || ! empty($attendance->marked_by ?? null);

        // A row that has only been clocked into (no clock-out) must still read
        // as "Checked In Only", not the 'present' stored for it — whether the
        // employee clocked in or it was hand-marked as clock-in only.
        if (! empty($attendance->clock_in ?? null) && empty($attendance->clock_out ?? null)) {
            return null;
        }

        $status = $isManual
            ? ($attendance->attendance_status ?? null)
            : ($attendance->effective_status ?? null);

        return match ($status) {
            'present', 'late', 'overtime', 'early_departure' => 'Present',
            'half_day' => 'halfday',
            'absent' => 'Absent',
            'on_leave' => 'Full Day Leave',
            'first_half_leave' => 'First Half Leave',
            'second_half_leave' => 'Second Half Leave',
            'holiday' => 'Holiday',
            'weekoff' => 'Week Off',
            default => null,
        };
    }

    /**
     * Determine user status for a given date - WITH SHIFT-BASED LOGIC
     */
    private function determineUserStatus($attendance, $leave, $holiday, $weekoff, $date, $userId)
    {
        // A hand-set / policy-resolved status wins over everything else.
        if ($attendance) {
            $persisted = $this->persistedDayStatus($attendance);
            if ($persisted !== null) {
                return $persisted;
            }
        }

        // HIGHEST PRIORITY: Attendance with shift-based calculation
        if ($attendance) {
            // ✅ FIX: Use worked_hours for calculation
            $totalHours = null;
            if ($attendance->worked_hours) {
                $totalHours = (float) $attendance->worked_hours;
            } elseif ($attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);
                $totalHours = $clockIn->diffInHours($clockOut);
            }

            if ($attendance->clock_in && $attendance->clock_out) {
                return $this->getAttendanceStatusByShift($totalHours, $userId, $date, $attendance);
            } elseif ($attendance->clock_in) {
                return 'Checked In Only';
            }
        }

        // THEN: Holiday
        if ($holiday) {
            return 'Holiday';
        }

        // THEN: Leave
        if ($leave) {
            if ($leave->start_session == 1 && $leave->end_session == 1 && $leave->start_date == $leave->start_date) {
                return 'First Half Leave';
            } elseif ($leave->start_session == 2 && $leave->end_session == 2 && $leave->start_date == $leave->start_date) {
                return 'Second Half Leave';
            } else {
                return 'Full Day Leave';
            }
        }

        // THEN: Week Off
        if ($weekoff) {
            if ($weekoff->off_type == 'day_based') {
                $dayName = Carbon::parse($date)->format('l');
                if ($weekoff->day_name == $dayName) {
                    return 'Week Off';
                }
            } else {
                return 'Week Off';
            }
        }

        // FINALLY: Absent
        return 'Absent';
    }

    /**
     * Get team members based on user role
     */
    private function getTeamMembers($authUser, $currentDate, $managerId = null, $search = null, $branchId = null, $departmentId = null, $designationId = null)
    {
        $query = User::query()
            ->with(['jobDetails.department', 'jobDetails.designation', 'jobDetails.branch', 'basicDetails'])
            ->where('status', 1)
            ->where('role', '!=', 'admin');

        if ($managerId !== null) {
            // Explicit target manager (e.g. viewing another manager's direct
            // reports from their profile page) — access to that page is
            // already gated by canViewUserProfile()/scopeCoversOwner(), so
            // the RBAC "my team" scope below doesn't apply here.
            $query->managedBy($managerId);
        } else {
            // Permission-based filtering
            $teamScope = app(RbacService::class)->scopeFor($authUser, 'team', 'view');
            if ($teamScope === 'team') {
                $query->managedBy($authUser->id);
            } elseif ($teamScope !== 'company') {
                return collect([]);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($branchId) {
            $query->whereHas('jobDetails', fn ($q) => $q->where('branch_id', $branchId));
        }

        if ($departmentId) {
            $query->whereHas('jobDetails', fn ($q) => $q->where('department', $departmentId));
        }

        if ($designationId) {
            $query->whereHas('jobDetails', fn ($q) => $q->where('designation', $designationId));
        }

        $users = $query->get();

        $attendanceRecords = Attendance::whereIn('user_id', $users->pluck('id'))
            ->whereDate('date', $currentDate)
            ->get()
            ->keyBy('user_id');

        // COALESCE(end_date, start_date) so legacy one-row-per-day records
        // (created before leave requests were collapsed to a single row and
        // never had end_date set) still match on their single day.
        $leaveRecords = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $currentDate)
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$currentDate])
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $holiday = Holiday::whereDate('start_date', '<=', $currentDate)
            ->whereDate('start_date', '>=', $currentDate)
            ->first();

        $weekoffs = UserWeekoffs::where('status', 1)
            ->whereIn('user_id', $users->pluck('id'))
            ->where(function ($query) use ($currentDate) {
                $query->where(function ($q) use ($currentDate) {
                    $q->where('off_type', 'date_based')
                        ->whereDate('start_date', '<=', $currentDate)
                        ->whereDate('end_date', '>=', $currentDate);
                })->orWhere(function ($q) use ($currentDate) {
                    $q->where('off_type', 'day_based')
                        ->where('day_name', Carbon::parse($currentDate)->format('l'));
                });
            })
            ->get()
            ->keyBy('user_id');

        // Process each user to add status and attendance info
        $teamData = collect();

        foreach ($users as $user) {
            $attendance = $attendanceRecords->get($user->id);
            $leave = $leaveRecords->get($user->id);
            $weekoff = $weekoffs->get($user->id);

            // Determine status with shift-based logic
            $status = $this->determineUserStatus(
                $attendance,
                $leave,
                $holiday,
                $weekoff,
                $currentDate,
                $user->id
            );

            // Create member object
            $member = new \stdClass;
            $member->id = $user->id;
            $member->employee_id = $user->employee_id;
            $member->name = $user->name;
            $member->email = $user->email;
            $member->designation = $user->jobDetails->Designation->name ?? null;
            $member->department = $user->jobDetails->Department->name ?? null;
            $member->branch = $user->jobDetails->branch->name ?? null;
            $member->profile_image = $user->basicDetails->profile_image ?? null;
            $member->punch_in = $attendance ? $attendance->clock_in : null;
            $member->punch_out = $attendance ? $attendance->clock_out : null;
            $member->total_hours = $attendance ? $attendance->total_hours : null;
            $member->status = $status;

            $teamData->push($member);
        }

        return $teamData;
    }

    private function getMonthOptions()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthValue = date('Y-m', mktime(0, 0, 0, $i, 1, date('Y')));
            $months[$monthValue] = date('F Y', mktime(0, 0, 0, $i, 1, date('Y')));
        }

        return $months;
    }

    private function getUserShiftForDate($userId, $date, $tenantId)
    {
        try {
            // The day's primary shift (fixed company shift when custom shifts are
            // off) — one implementation in TenantShiftResolver.
            return app(\App\Services\Attendance\TenantShiftResolver::class)
                ->detailsForUserDate((int) $userId, (int) $tenantId, $date);
        } catch (Exception $e) {
            Log::error('Error getting user shift: '.$e->getMessage());

            return null;
        }
    }
}
