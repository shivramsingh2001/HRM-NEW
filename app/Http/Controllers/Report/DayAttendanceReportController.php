<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daywise Attendance (one day, every employee) + its CSV export.
 * Moved out of AttendanceReportController unchanged (code-quality plan, Phase 3);
 * route names are the same.
 */
class DayAttendanceReportController extends Controller
{
    use \App\Http\Controllers\Concerns\AttendanceReportHelpers;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use SanitizesCsv;

    /**
     * Day Attendance Report (Single Day)
     */
    public function dayAttendanceReport(Request $request)
    {
        try {
            // Get tenant_id from session
            $tenantId = session('tenant_id');

            if (! $tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            // Get date from request - default to today
            $selectedDate = $request->get('date', now()->format('Y-m-d'));
            $dateObj = Carbon::parse($selectedDate);

            // For single day
            $dayStart = $dateObj->format('Y-m-d');
            $dayEnd = $dateObj->format('Y-m-d');

            // Get filter values
            $search = $request->get('search');
            $statusFilter = $request->get('status');
            $userIdFilter = $request->get('user_id');
            $branchFilter = $request->get('branch_id');

            // Fetch all active employees (non-admin) with tenant filter
            $users = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('attendance_locations as al', 'uj.office_branch', '=', 'al.id')
                ->leftJoin('user_basic_details as ub', 'u.id', '=', 'ub.user_id')
                ->leftJoin('company_branches as cb', 'uj.branch_id', '=', 'cb.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'u.role',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.office_branch',
                    'al.name as location_name',
                    'uj.reporting_head',
                    'uj.branch_id',
                    'cb.name as branch_name',
                    'ub.profile_image'
                )
                ->tap(fn ($q) => $this->applyReportEmployeeFilters($q, $request))
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->get();

            // Apply user filter if provided
            if ($userIdFilter) {
                $users = $users->where('id', (int) $userIdFilter);
            }

            // Apply branch filter
            if ($branchFilter) {
                $users = $users->where('branch_id', (int) $branchFilter);
            }

            // Apply search filter to users first
            if ($search) {
                $users = $users->filter(function ($user) use ($search) {
                    return stripos($user->name, $search) !== false ||
                        stripos($user->employee_id, $search) !== false;
                });
            }

            // Fetch attendance records for the day with tenant filter
            $attendanceRows = DB::table('attendances as a')
                ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('attendance_locations as al', 'uj.office_branch', '=', 'al.id')
                ->select(
                    'a.user_id',
                    'a.date',
                    'a.clock_in',
                    'a.clock_out',
                    'a.total_hours',
                    'a.worked_hours',
                    'a.clock_in_address as clockinlocation',
                    'a.clock_out_address as clockoutlocation',
                    'a.clock_in_lat',
                    'a.clock_in_long',
                    'a.clock_out_lat',
                    'a.clock_out_long',
                    'a.scheduled_shift_start as shiftstarttime',
                    'a.scheduled_shift_end as shiftendtime',
                    'a.attendance_status',
                    'a.effective_status',
                    'a.attendance_type',
                    'a.marked_by',
                    'a.late_minutes',
                    'a.early_departure_minutes as earlyexitminutes',
                    'a.overtime_minutes',
                    'a.extra_shift_minutes',
                    'a.shift_count',
                    'a.id as attendance_id',
                    'a.remarks',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.office_branch',
                    'al.name as location_name'
                )
                ->where('a.tenant_id', $tenantId)
                ->where('a.date', $dayStart)
                ->get()
                ->keyBy('user_id');

            // Multi-shift: per-shift breakdown for days worked across several shifts.
            $segmentRows = DB::table('attendance_shift_segments as sg')
                ->leftJoin('shifts as sh', 'sh.id', '=', 'sg.shift_id')
                ->where('sg.tenant_id', $tenantId)
                ->where('sg.date', $dayStart)
                ->orderBy('sg.is_additional')
                ->orderBy('sg.scheduled_start')
                ->get(['sg.*', 'sh.name as shift_name'])
                ->groupBy('attendance_id');

            // Fetch approved leaves for this day
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($dayStart) {
                    $q->where('start_date', '<=', $dayStart)
                        ->where('end_date', '>=', $dayStart);
                })
                ->get()
                ->keyBy('user_id');

            // Fetch holidays for this day
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where('start_date', '<=', $dayStart)
                ->where('end_date', '>=', $dayStart)
                ->get();

            // Fetch user weekoffs with tenant filter
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get();

            // Fetch approved overtime for this day
            $overtimeRows = DB::table('overtime_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('date', $dayStart)
                ->get()
                ->keyBy('user_id');

            // Build the report
            $report = [];

            foreach ($users as $user) {
                // Get attendance for this user
                $attendance = $attendanceRows->get($user->id);

                // Check if user is on leave
                $leave = $leaveRows->get($user->id);

                // Check if it's a holiday
                $holiday = $holidayRows->first();

                // Check if it's a week off
                $weekoff = $weekoffRows->first(function ($wo) use ($user, $dateObj) {
                    if ($wo->user_id != $user->id) {
                        return false;
                    }

                    if ($wo->off_type == 'day_based') {
                        return strtolower($wo->day_name) == strtolower($dateObj->format('l'));
                    }

                    if ($wo->off_type == 'date_based') {
                        return $dateObj->format('Y-m-d') >= $wo->start_date &&
                            $dateObj->format('Y-m-d') <= $wo->end_date;
                    }

                    return false;
                });

                // Check if user has approved overtime for this date
                $overtime = $overtimeRows->get($user->id);

                // Calculate total hours from worked_hours
                $totalHours = null;
                if ($attendance && $attendance->worked_hours) {
                    $totalHours = (float) $attendance->worked_hours;
                } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);
                    $totalHours = $clockIn->diffInHours($clockOut);
                }

                // Determine status using shift-based logic
                $status = 'absent';
                $persistedToken = $attendance ? $this->persistedReportToken($attendance) : null;
                if ($persistedToken !== null) {
                    $status = $persistedToken;
                } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    // Multi-shift: the primary shift's hours decide the status.
                    $primaryHours = $totalHours !== null
                        ? app(\App\Services\Attendance\AttendanceCalculator::class)->primaryWorkedSeconds($attendance, (int) round($totalHours * 3600)) / 3600
                        : null;
                    $status = $this->getAttendanceStatusByShift($primaryHours, $user->id, $dayStart, $attendance);
                    $status = strtolower($status);
                } elseif ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
                    $status = 'checked_in_only';
                } elseif ($holiday) {
                    $status = 'holiday';
                } elseif ($leave) {
                    $status = 'on_leave';
                } elseif ($weekoff) {
                    $status = 'week_off';
                }

                // Multi-shift: every shift worked that day (primary first).
                $daySegments = ($attendance && (int) ($attendance->shift_count ?? 0) > 1)
                    ? $segmentRows->get($attendance->attendance_id, collect())
                    : collect();

                // Build report entry
                $report[] = [
                    'user_id' => $user->id,
                    'employee_id' => $user->employee_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->department_name,
                    'designation' => $user->designation_name,
                    'branch' => $user->branch_name,
                    'attendance_location' => \App\Models\AttendanceLocation::labelFor($user->office_branch, $user->location_name),
                    'date' => $dayStart,
                    'day' => $dateObj->format('D'),
                    'status' => $status,
                    'clock_in' => $attendance->clock_in ?? null,
                    'clock_out' => $attendance->clock_out ?? null,
                    'total_hours' => $attendance->total_hours ?? null,
                    'worked_hours' => $attendance->worked_hours ?? null,
                    'clock_in_location' => $attendance->clockinlocation ?? null,
                    'clock_out_location' => $attendance->clockoutlocation ?? null,
                    'clock_in_lat' => $attendance->clock_in_lat ?? null,
                    'clock_in_lng' => $attendance->clock_in_long ?? null,
                    'clock_out_lat' => $attendance->clock_out_lat ?? null,
                    'clock_out_lng' => $attendance->clock_out_long ?? null,
                    'shift_start_time' => $attendance->shiftstarttime ?? null,
                    'shift_end_time' => $attendance->shiftendtime ?? null,
                    'late_minutes' => $attendance->late_minutes ?? 0,
                    'early_exit_minutes' => $attendance->earlyexitminutes ?? 0,
                    'overtime_minutes' => ($overtime ? ($overtime->approved_hours * 60) : 0) + (int) ($attendance->extra_shift_minutes ?? 0),
                    'overtime_hours' => $overtime ? $overtime->approved_hours : null,
                    'overtime_reason' => $overtime ? $overtime->reason : null,
                    'extra_shift_minutes' => (int) ($attendance->extra_shift_minutes ?? 0),
                    'shifts' => $daySegments->map(fn ($seg) => [
                        'name' => $seg->shift_name,
                        'start' => $seg->scheduled_start,
                        'end' => $seg->scheduled_end,
                        'worked_minutes' => (int) $seg->worked_minutes,
                        'is_additional' => (bool) $seg->is_additional,
                    ])->values()->all(),
                    'remarks' => $attendance->remarks ?? null,
                    'leave_type' => $leave->leave_type ?? null,
                    'holiday_name' => $holiday->name ?? null,
                    'weekoff_type' => $weekoff->off_type ?? null,
                ];
            }

            $reportCollection = collect($report);

            // ✅ Apply status filter - WITH COMBINED PRESENT
            if ($statusFilter) {
                $reportCollection = $reportCollection->filter(function ($item) use ($statusFilter) {
                    $status = $item['status'];
                    switch ($statusFilter) {
                        case 'present':
                            return in_array($status, ['present', 'halfday', 'checked_in_only']);
                        case 'halfday':
                            return $status === 'halfday';
                        case 'checked_in_only':
                            return $status === 'checked_in_only';
                        case 'absent':
                            return $status === 'absent';
                        case 'on_leave':
                            return str_contains($status, 'leave');
                        case 'holiday':
                            return $status === 'holiday';
                        case 'weekoff':
                            return $status === 'week_off';
                        default:
                            return $status === $statusFilter;
                    }
                });
            }

            // ✅ Calculate stats - WITH SEPARATE COUNTS
            $stats = [
                'totalEmployees' => $reportCollection->count(),
                'presentCount' => $reportCollection->whereIn('status', ['present', 'halfday', 'checked_in_only'])->count(),
                'halfDayCount' => $reportCollection->where('status', 'halfday')->count(),
                'checkedInOnlyCount' => $reportCollection->where('status', 'checked_in_only')->count(),
                'absentCount' => $reportCollection->where('status', 'absent')->count(),
                'leaveCount' => $reportCollection->where('status', 'on_leave')->count(),
                'weekOffCount' => $reportCollection->where('status', 'week_off')->count(),
                'holidayCount' => $reportCollection->where('status', 'holiday')->count(),
            ];

            // Paginate the report data
            $perPage = $request->get('per_page', 50);
            $currentPage = $request->get('page', 1);
            $total = $reportCollection->count();
            $paginated = $reportCollection->slice(($currentPage - 1) * $perPage, $perPage);

            // Get unique employees for dropdown with tenant filter
            $employees = DB::table('users')
                ->select('id', 'name', 'employee_id', 'email')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->where('role', '!=', 'admin')
                ->orderBy('name')
                ->get();

            $branches = DB::table('company_branches')
                ->where('tenant_id', $tenantId)->where('status', 1)
                ->select('id', 'name')->orderBy('name')->get();

            return view('client.report.attendance.attendance-daily', [
                'reportData' => new \Illuminate\Pagination\LengthAwarePaginator(
                    $paginated,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => route('report.attendance.day.index'), 'query' => $request->query()]
                ),
                'stats' => $stats,
                'employees' => $employees,
                'branches' => $branches,
                'selectedDate' => $dayStart,
                'selectedDateObj' => $dateObj,
                'monthStart' => $dayStart,
                'monthEnd' => $dayStart,
            ]);
        } catch (Exception $e) {

            Log::error('Attendance Report Error: '.$e->getMessage());
            Log::error('Trace: '.$e->getTraceAsString());

            return back()->with('error', 'Failed to generate attendance report: '.$e->getMessage());
        }
    }

    /**
     * Export Day Attendance Report to CSV
     */
    public function dayExportAttendance(Request $request)
    {
        try {
            // Get tenant_id from session
            $tenantId = session('tenant_id');

            if (! $tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            // Get date from request - default to today
            $selectedDate = $request->get('date', now()->format('Y-m-d'));
            $dateObj = Carbon::parse($selectedDate);
            $dayStart = $dateObj->format('Y-m-d');

            // Get filter values
            $search = $request->get('search');
            $statusFilter = $request->get('status');
            $userIdFilter = $request->get('user_id');
            $branchFilter = $request->get('branch_id');

            // Fetch all active employees (non-admin) with tenant filter
            $users = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('attendance_locations as al', 'uj.office_branch', '=', 'al.id')
                ->leftJoin('company_branches as cb', 'uj.branch_id', '=', 'cb.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.office_branch',
                    'al.name as location_name',
                    'uj.branch_id',
                    'cb.name as branch_name'
                )
                ->tap(fn ($q) => $this->applyReportEmployeeFilters($q, $request))
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->get();

            // Apply user filter if provided
            if ($userIdFilter) {
                $users = $users->where('id', (int) $userIdFilter);
            }

            // Apply branch filter
            if ($branchFilter) {
                $users = $users->where('branch_id', (int) $branchFilter);
            }

            // Apply search filter to users
            if ($search) {
                $users = $users->filter(function ($user) use ($search) {
                    return stripos($user->name, $search) !== false ||
                        stripos($user->employee_id, $search) !== false;
                });
            }

            // Fetch attendance records for the day with tenant filter
            $attendanceRows = DB::table('attendances as a')
                ->where('a.tenant_id', $tenantId)
                ->where('a.date', $dayStart)
                ->get()
                ->keyBy('user_id');

            // Multi-shift: per-shift breakdown for days worked across several shifts.
            $segmentRows = DB::table('attendance_shift_segments as sg')
                ->leftJoin('shifts as sh', 'sh.id', '=', 'sg.shift_id')
                ->where('sg.tenant_id', $tenantId)
                ->where('sg.date', $dayStart)
                ->orderBy('sg.is_additional')
                ->orderBy('sg.scheduled_start')
                ->get(['sg.*', 'sh.name as shift_name'])
                ->groupBy('attendance_id');

            // Fetch approved leaves for this day
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('start_date', '<=', $dayStart)
                ->where('end_date', '>=', $dayStart)
                ->get()
                ->keyBy('user_id');

            // Fetch holidays for this day
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where('start_date', '<=', $dayStart)
                ->where('end_date', '>=', $dayStart)
                ->get();

            // Fetch user weekoffs with tenant filter
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get();

            // Fetch approved overtime for this day
            $overtimeRows = DB::table('overtime_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('date', $dayStart)
                ->get()
                ->keyBy('user_id');

            // Build complete report data
            $reportData = [];

            foreach ($users as $user) {
                $attendance = $attendanceRows->get($user->id);
                $leave = $leaveRows->get($user->id);
                $holiday = $holidayRows->first();
                $overtime = $overtimeRows->get($user->id);

                // Check if it's a week off
                $weekoff = $weekoffRows->first(function ($wo) use ($user, $dateObj) {
                    if ($wo->user_id != $user->id) {
                        return false;
                    }

                    if ($wo->off_type == 'day_based') {
                        return strtolower($wo->day_name) == strtolower($dateObj->format('l'));
                    }

                    if ($wo->off_type == 'date_based') {
                        return $dateObj->format('Y-m-d') >= $wo->start_date &&
                            $dateObj->format('Y-m-d') <= $wo->end_date;
                    }

                    return false;
                });

                // Calculate total hours from worked_hours
                $totalHours = null;
                if ($attendance && $attendance->worked_hours) {
                    $totalHours = (float) $attendance->worked_hours;
                } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);
                    $totalHours = $clockIn->diffInHours($clockOut);
                }

                // Determine status using shift-based logic
                $status = 'absent';
                $persistedToken = $attendance ? $this->persistedReportToken($attendance) : null;
                if ($persistedToken !== null) {
                    $status = $persistedToken;
                } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    // Multi-shift: the primary shift's hours decide the status.
                    $primaryHours = $totalHours !== null
                        ? app(\App\Services\Attendance\AttendanceCalculator::class)->primaryWorkedSeconds($attendance, (int) round($totalHours * 3600)) / 3600
                        : null;
                    $status = $this->getAttendanceStatusByShift($primaryHours, $user->id, $dayStart, $attendance);
                    $status = strtolower($status);
                } elseif ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
                    $status = 'checked_in_only';
                } elseif ($holiday) {
                    $status = 'holiday';
                } elseif ($leave) {
                    $status = 'on_leave';
                } elseif ($weekoff) {
                    $status = 'week_off';
                }

                // Multi-shift: every shift worked that day (primary first).
                $daySegments = ($attendance && (int) ($attendance->shift_count ?? 0) > 1)
                    ? $segmentRows->get($attendance->id, collect())
                    : collect();

                // ✅ Apply status filter - WITH COMBINED PRESENT
                if ($statusFilter) {
                    switch ($statusFilter) {
                        case 'present':
                            if (! in_array($status, ['present', 'halfday', 'checked_in_only'])) {
                                continue 2;
                            }
                            break;
                        case 'halfday':
                            if ($status !== 'halfday') {
                                continue 2;
                            }
                            break;
                        case 'checked_in_only':
                            if ($status !== 'checked_in_only') {
                                continue 2;
                            }
                            break;
                        case 'absent':
                            if ($status !== 'absent') {
                                continue 2;
                            }
                            break;
                        case 'on_leave':
                            if (! str_contains($status, 'leave')) {
                                continue 2;
                            }
                            break;
                        case 'holiday':
                            if ($status !== 'holiday') {
                                continue 2;
                            }
                            break;
                        case 'weekoff':
                            if ($status !== 'week_off') {
                                continue 2;
                            }
                            break;
                        default:
                            if ($status !== $statusFilter) {
                                continue 2;
                            }
                            break;
                    }
                }

                $reportData[] = [
                    'user_id' => $user->id,
                    'employee_name' => $user->name,
                    'employee_id' => $user->employee_id ?? 'N/A',
                    'department' => $user->department_name ?? 'N/A',
                    'designation' => $user->designation_name ?? 'N/A',
                    'branch' => $user->branch_name ?? 'N/A',
                    'attendance_location' => \App\Models\AttendanceLocation::labelFor($user->office_branch, $user->location_name),
                    'date' => $dayStart,
                    'day' => $dateObj->format('D'),
                    'status' => $status,
                    'clock_in' => $attendance->clock_in ?? null,
                    'clock_out' => $attendance->clock_out ?? null,
                    'total_hours' => $attendance->total_hours ?? null,
                    'worked_hours' => $attendance->worked_hours ?? null,
                    'clock_in_location' => $attendance->clock_in_address ?? null,
                    'clock_out_location' => $attendance->clock_out_address ?? null,
                    'shift_start_time' => $attendance->scheduled_shift_start ?? null,
                    'shift_end_time' => $attendance->scheduled_shift_end ?? null,
                    'late_minutes' => $attendance->late_minutes ?? 0,
                    'early_exit_minutes' => $attendance->early_departure_minutes ?? 0,
                    'overtime_minutes' => ($overtime ? ($overtime->approved_hours * 60) : 0) + (int) ($attendance->extra_shift_minutes ?? 0),
                    'overtime_hours' => $overtime ? $overtime->approved_hours : null,
                    'overtime_reason' => $overtime ? $overtime->reason : null,
                    'extra_shift_minutes' => (int) ($attendance->extra_shift_minutes ?? 0),
                    'shifts' => $daySegments->map(fn ($seg) => [
                        'name' => $seg->shift_name,
                        'start' => $seg->scheduled_start,
                        'end' => $seg->scheduled_end,
                        'worked_minutes' => (int) $seg->worked_minutes,
                        'is_additional' => (bool) $seg->is_additional,
                    ])->values()->all(),
                    'remarks' => $attendance->remarks ?? null,
                    'leave_type' => $leave->leave_type ?? null,
                    'holiday_name' => $holiday->name ?? null,
                    'weekoff_type' => $weekoff->off_type ?? null,
                ];
            }

            $reportCollection = collect($reportData);

            // Generate CSV
            $filename = 'attendance_report_'.$dayStart.'.csv';

            // Create a temporary file handle
            $handle = fopen('php://temp', 'w+');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            $this->writeCsvRow($handle, [
                'SR NO.',
                'Employee Name',
                'Employee ID',
                'Department',
                'Designation',
                'Branch',
                'Attendance Location',
                'Date',
                'Day',
                'Status',
                'Clock In',
                'Clock Out',
                'Total Hours',
                'Shift',
                'Clock In Location',
                'Clock Out Location',
                'Late (mins)',
                'Early Exit (mins)',
                'OT (mins)',
                'OT Hours',
                'OT Reason',
                'Remarks',
            ]);

            // Data rows
            $srNo = 1;
            foreach ($reportCollection as $row) {
                // Format status for display
                $statusLabels = [
                    'present' => 'Present',
                    'absent' => 'Absent',
                    'on_leave' => 'On Leave'.($row['leave_type'] ? ' ('.$row['leave_type'].')' : ''),
                    'holiday' => 'Holiday'.($row['holiday_name'] ? ' ('.$row['holiday_name'].')' : ''),
                    'week_off' => 'Week Off',
                    'checked_in_only' => 'Checked In Only',
                    'halfday' => 'Half Day',
                    'first_half' => 'First Half (Morning)',
                    'second_half' => 'Second Half (Afternoon)',
                    'first_half_leave' => 'First Half Leave',
                    'second_half_leave' => 'Second Half Leave',
                    'full_day_leave' => 'Full Day Leave',
                ];
                $statusLabel = $statusLabels[$row['status']] ?? ucfirst(str_replace('_', ' ', $row['status']));

                // Format shift times
                $shift = '—';
                if ($row['shift_start_time'] || $row['shift_end_time']) {
                    $start = $row['shift_start_time']
                        ? Carbon::parse($row['shift_start_time'])->format('h:i A')
                        : '--';
                    $end = $row['shift_end_time']
                        ? Carbon::parse($row['shift_end_time'])->format('h:i A')
                        : '--';
                    $shift = $start.' - '.$end;
                }
                // Multi-shift: list every shift worked that day.
                if (! empty($row['shifts'])) {
                    $shift = collect($row['shifts'])->map(fn ($sg) => ($sg['is_additional'] ? '+ ' : '')
                        .($sg['name'] ?? 'Shift').' '.Carbon::parse($sg['start'])->format('h:i A').' - '
                        .Carbon::parse($sg['end'])->format('h:i A').' ('.round($sg['worked_minutes'] / 60, 2).' hrs)')
                        ->implode(' | ');
                }

                // Format clock in/out
                $clockIn = $row['clock_in']
                    ? Carbon::parse($row['clock_in'])->format('d M Y h:i A')
                    : '—';
                $clockOut = $row['clock_out']
                    ? Carbon::parse($row['clock_out'])->format('d M Y h:i A')
                    : '—';

                $this->writeCsvRow($handle, [
                    $srNo++,
                    $row['employee_name'],
                    $row['employee_id'],
                    $row['department'],
                    $row['designation'],
                    $row['branch'],
                    $row['attendance_location'],
                    Carbon::parse($row['date'])->format('d M Y'),
                    $row['day'],
                    $statusLabel,
                    $clockIn,
                    $clockOut,
                    $row['worked_hours'] ? number_format((float) $row['worked_hours'], 2).' hrs' : ($row['total_hours'] ? $row['total_hours'] : '—'),
                    $shift,
                    $row['clock_in_location'] ?? '—',
                    $row['clock_out_location'] ?? '—',
                    $row['late_minutes'] ?? 0,
                    $row['early_exit_minutes'] ?? 0,
                    $row['overtime_minutes'] ?? 0,
                    $row['overtime_hours'] ? number_format((float) $row['overtime_hours'], 2).' hrs' : '—',
                    $row['overtime_reason'] ?? '—',
                    $row['remarks'] ?? '—',
                ]);
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            return response($csvContent, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (Exception $e) {
            Log::error('Export Error: '.$e->getMessage());
            Log::error('Trace: '.$e->getTraceAsString());

            return back()->with('error', 'Failed to export: '.$e->getMessage());
        }
    }
}
