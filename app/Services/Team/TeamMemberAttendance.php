<?php

namespace App\Services\Team;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use App\Models\UserWeekoffs;
use App\Traits\AuthorizesByScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A team member's attendance for the member profile page — day rows with status,
 * monthly summary, calendar events, task counts. Moved out of TeamController
 * unchanged (code-quality plan, Phase 3); used by TeamMemberController.
 */
class TeamMemberAttendance
{
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use \App\Http\Controllers\Concerns\SanitizesCsv;
    use \App\Http\Controllers\Concerns\TeamAttendanceStatus;
    use AuthorizesByScope;

    /**
     * Get attendance data for user within date range - WITH SHIFT-BASED LOGIC
     */
    public function getAttendanceDataForUser($startDate, $endDate, $userId)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $currentDateObj = Carbon::now()->startOfDay();
        $dates = [];

        $user = User::find($userId);
        if (! $user) {
            return collect([]);
        }

        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy('date');

        // Overlap check (with COALESCE fallback for legacy one-row-per-day
        // records that never had end_date set) instead of matching start_date
        // alone, so every day of a multi-day leave is picked up below.
        $leaves = Leave::where('user_id', $userId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endDate)
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$startDate])
            ->get();

        $holidays = Holiday::where('start_date', '<=', $endDate)
            ->where('start_date', '>=', $startDate)
            ->get();

        $weekoffs = UserWeekoffs::where('user_id', $userId)
            ->where('status', 1)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $currentDate = Carbon::parse($dateStr);
            $isFutureDate = $currentDate->gt($currentDateObj);

            $attendance = isset($attendances[$dateStr]) ? $attendances[$dateStr] : null;

            // ✅ FIX: Use worked_hours for calculation
            $totalHours = null;
            if ($attendance && $attendance->worked_hours) {
                $totalHours = (float) $attendance->worked_hours;
            } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);
                $totalHours = $clockIn->diffInHours($clockOut);
            }

            // Check holiday
            $holiday = null;
            foreach ($holidays as $h) {
                $holidayStart = Carbon::parse($h->start_date)->startOfDay();
                $holidayEnd = Carbon::parse($h->start_date)->endOfDay();
                if ($currentDate->between($holidayStart, $holidayEnd)) {
                    $holiday = $h;
                    break;
                }
            }

            // Check leave
            $leave = null;
            foreach ($leaves as $l) {
                $leaveStart = Carbon::parse($l->start_date)->startOfDay();
                $leaveEnd = Carbon::parse($l->end_date ?? $l->start_date)->endOfDay();
                if ($currentDate->between($leaveStart, $leaveEnd)) {
                    $leave = $l;
                    break;
                }
            }

            // Check weekoff
            $weekoff = null;
            foreach ($weekoffs as $w) {
                $weekoffStart = Carbon::parse($w->start_date)->startOfDay();
                $weekoffEnd = Carbon::parse($w->end_date)->endOfDay();
                if ($currentDate->between($weekoffStart, $weekoffEnd)) {
                    if ($w->off_type == 'date_based') {
                        $weekoff = $w;
                        break;
                    } elseif ($w->off_type == 'day_based' && $w->day_name == $date->format('l')) {
                        $weekoff = $w;
                        break;
                    }
                }
            }

            // Determine status - WITH SHIFT-BASED LOGIC
            $status = 'Absent';

            if ($isFutureDate) {
                $status = 'Upcoming';
                if ($holiday) {
                    $status = 'Holiday';
                } elseif ($leave) {
                    if ($leave->start_date == $leave->start_date) {
                        if ($leave->start_session == 1 && $leave->end_session == 1) {
                            $status = 'First Half Leave';
                        } elseif ($leave->start_session == 2 && $leave->end_session == 2) {
                            $status = 'Second Half Leave';
                        } else {
                            $status = 'Full Day Leave';
                        }
                    } else {
                        $status = 'Full Day Leave';
                    }
                } elseif ($weekoff) {
                    $status = 'Week Off';
                }
            } else {
                // Updated priority with shift-based logic
                if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    $status = $this->getAttendanceStatusByShift($totalHours, $userId, $dateStr, $attendance);
                } elseif ($attendance && $attendance->clock_in) {
                    $status = 'Checked In Only';
                } elseif ($holiday) {
                    $status = 'Holiday';
                } elseif ($leave) {
                    if ($leave->start_date == $leave->start_date) {
                        if ($leave->start_session == 1 && $leave->end_session == 1) {
                            $status = 'First Half Leave';
                        } elseif ($leave->start_session == 2 && $leave->end_session == 2) {
                            $status = 'Second Half Leave';
                        } else {
                            $status = 'Full Day Leave';
                        }
                    } else {
                        $status = 'Full Day Leave';
                    }
                } elseif ($weekoff) {
                    $status = 'Week Off';
                }
            }

            $record = new \stdClass;
            $record->user_id = $userId;
            $record->name = $user->name;
            $record->email = $user->email;
            $record->date = $dateStr;
            $record->formatted_date = $dateStr;
            $record->day_name = $date->format('l');
            $record->clock_in = (! $isFutureDate && $attendance) ? $attendance->clock_in : null;
            $record->clock_out = (! $isFutureDate && $attendance) ? $attendance->clock_out : null;
            $record->total_hours = (! $isFutureDate && $attendance) ? number_format($totalHours, 2) : null;
            $record->leave_type = $leave->leave_type ?? null;
            $record->leave_reason = $leave->reason ?? null;
            $record->leave_session = $leave->start_session ?? null;
            $record->holiday_name = $holiday->name ?? null;
            $record->message = $holiday->name ?? null;
            $record->off_type = $weekoff->off_type ?? null;
            $record->weekoff_day = $weekoff->day_name ?? null;
            $record->has_weekoff = $weekoff ? true : false;
            $record->day_status = $status;

            $dates[] = $record;
        }

        usort($dates, function ($a, $b) {
            return strtotime($b->date) - strtotime($a->date);
        });

        return collect($dates);
    }

    /**
     * Calculate summary from attendance data
     */
    public function calculateSummary($attendanceData)
    {
        $summary = [
            'present' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'holiday' => 0,
            'week_off' => 0,
            'checked_in_only' => 0,
            'halfday' => 0,
            'work_days' => 0,
            'total_days' => 0,
        ];

        $uniqueDates = [];
        $holidayDates = [];
        $weekOffDates = [];

        foreach ($attendanceData as $record) {
            $dateStr = $record->date;

            if (! in_array($dateStr, $uniqueDates)) {
                $uniqueDates[] = $dateStr;
            }

            switch ($record->day_status) {
                case 'Present':
                    $summary['present']++;
                    break;
                case 'halfday':
                    // 0.5 present only — do not also add 0.5 to absent.
                    $summary['halfday']++;
                    $summary['present'] += 0.5;
                    break;
                case 'Checked In Only':
                    $summary['checked_in_only']++;
                    $summary['present']++;
                    break;
                case 'Absent':
                    $summary['absent']++;
                    break;
                case 'Holiday':
                    $summary['holiday']++;
                    if (! in_array($dateStr, $holidayDates)) {
                        $holidayDates[] = $dateStr;
                    }
                    break;
                case 'Week Off':
                    $summary['week_off']++;
                    if (! in_array($dateStr, $weekOffDates)) {
                        $weekOffDates[] = $dateStr;
                    }
                    break;
                case 'First Half Leave':
                case 'Second Half Leave':
                case 'Full Day Leave':
                    $summary['on_leave']++;
                    break;
            }
        }

        $summary['total_days'] = count($uniqueDates);
        $summary['work_days'] = $summary['total_days'] - count($holidayDates) - count($weekOffDates);

        return $summary;
    }

    /**
     * Create calendar event from attendance record
     */
    public function createCalendarEvent($record)
    {
        $title = $this->getEventTitle($record);
        $color = $this->getEventColor($record->day_status);

        return [
            'id' => 'att_'.$record->formatted_date,
            'title' => $title,
            'start' => $record->formatted_date,
            'end' => $record->formatted_date,
            'day_status' => $record->day_status,
            'bgColor' => $color,
            'color' => '#ffffff',
            'clock_in' => $record->clock_in,
            'clock_out' => $record->clock_out,
            'total_hours' => $record->total_hours,
            'leave_type' => $record->leave_type,
            'holiday_name' => $record->holiday_name,
            'leave_reason' => $record->leave_reason,
            'has_weekoff' => $record->has_weekoff ?? false,
            'weekoff_day' => $record->weekoff_day ?? null,
            'isAllday' => true,
            'borderColor' => $color,
            'dragBgColor' => $color,
        ];
    }

    /**
     * Get event title for calendar
     */
    private function getEventTitle($record)
    {
        switch ($record->day_status) {
            case 'Present':
                $time = ($record->clock_in ? substr($record->clock_in, 0, 5) : '').
                    ($record->clock_out ? ' - '.substr($record->clock_out, 0, 5) : '');
                $hoursText = $record->total_hours ? ' ('.$record->total_hours.' hrs)' : '';

                return 'Present'.$hoursText.($time ? " ($time)" : '');
            case 'halfday':
                $time = ($record->clock_in ? substr($record->clock_in, 0, 5) : '').
                    ($record->clock_out ? ' - '.substr($record->clock_out, 0, 5) : '');
                $hoursText = $record->total_hours ? ' ('.$record->total_hours.' hrs)' : '';

                return 'halfday'.$hoursText.($time ? " ($time)" : '');
            case 'Holiday':
                return $record->holiday_name ?: 'Holiday';
            case 'First Half Leave':
                return 'First Half - '.($record->leave_type ?: 'Leave');
            case 'Second Half Leave':
                return 'Second Half - '.($record->leave_type ?: 'Leave');
            case 'Full Day Leave':
                return 'Leave - '.($record->leave_type ?: 'Leave');
            case 'Checked In Only':
                $time = $record->clock_in ? substr($record->clock_in, 0, 5) : '';

                return 'Checked In'.($time ? " ($time)" : '');
            case 'Week Off':
                $title = 'Week Off';
                if ($record->off_type == 'day_based' && $record->weekoff_day) {
                    $title .= ' ('.$record->weekoff_day.')';
                }

                return $title;
            case 'Absent':
                return 'Absent';
            default:
                return $record->day_status;
        }
    }

    /**
     * Get event color based on status
     */
    private function getEventColor($status)
    {
        $colorMap = [
            'Present' => '#28a745',
            'halfday' => '#ffc107',
            'Checked In Only' => '#ffc107',
            'Holiday' => '#0d6efd',
            'First Half Leave' => '#fd7e14',
            'Second Half Leave' => '#fd7e14',
            'Full Day Leave' => '#fd7e14',
            'Week Off' => '#6c757d',
            'Absent' => '#dc3545',
            'Upcoming' => '#17a2b8',
        ];

        return $colorMap[$status] ?? '#6f42c1';
    }

    public function addTaskCountsToAttendance($attendanceData, $userId)
    {
        foreach ($attendanceData as $record) {
            $date = $record->date;

            $taskCount = DB::selectOne('
                SELECT COUNT(DISTINCT t.id) as count
                FROM tasks t
                INNER JOIN task_assigns ta ON t.id = ta.task_id
                WHERE ta.assigned_to = ?
                    AND t.task_date <= ?
                    AND t.deadline_date >= ?
            ', [$userId, $date, $date]);

            $record->task_count = $taskCount ? (int) $taskCount->count : 0;
        }

        return $attendanceData;
    }
}
