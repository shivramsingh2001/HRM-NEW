<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Detailed Report (every employee × every day of a date range) + its CSV export.
 * Moved out of AttendanceReportController unchanged (code-quality plan, Phase 3);
 * route names are the same.
 */
class DetailAttendanceReportController extends Controller
{
    use \App\Http\Controllers\Concerns\AttendanceReportHelpers;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use SanitizesCsv;

    /**
     * Detail Attendance Report (Monthly)
     */
    public function detailAttendanceReport(Request $request)
    {
        try {
            // Get tenant_id from session
            $tenantId = session('tenant_id');

            if (! $tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');

            // ✅ If selected month is current month, limit to today
            $today = now()->format('Y-m-d');
            if ($selectedDate->format('Y-m') == now()->format('Y-m')) {
                $monthEnd = $today;
            } else {
                $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            }

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
                ->when($branchFilter, fn ($q) => $q->where('uj.branch_id', (int) $branchFilter))
                ->when($search, fn ($q) => $q->where(function ($qq) use ($search) {
                    $qq->where('u.name', 'LIKE', '%'.$search.'%')->orWhere('u.employee_id', 'LIKE', '%'.$search.'%');
                }))
                ->get();

            // Fetch attendance records for the month with tenant filter
            $attendanceRows = DB::table('attendances as a')
                ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('attendance_locations as al', 'uj.office_branch', '=', 'al.id')
                ->leftJoin('company_branches as cb', 'uj.branch_id', '=', 'cb.id')
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
                    'a.remarks',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.office_branch',
                    'al.name as location_name',
                    'cb.name as branch_name'
                )
                ->where('a.tenant_id', $tenantId)
                ->whereBetween('a.date', [$monthStart, $monthEnd])
                ->get();

            // Fetch approved leaves for the month with tenant filter
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch holidays for the month with tenant filter
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch user weekoffs with tenant filter
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get();

            // Fetch approved overtime requests for the month with tenant filter
            $overtimeRows = DB::table('overtime_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get();

            // Build the report
            $report = [];

            foreach ($users as $user) {
                // Get attendances for this user
                $userAttendances = $attendanceRows->groupBy(function ($row) {
                    return $row->user_id.'_'.$row->date;
                })->filter(function ($row) use ($user) {
                    $firstRow = $row->first();

                    return $firstRow && $firstRow->user_id == $user->id;
                });

                // Get overtime for this user
                $userOvertime = $overtimeRows->where('user_id', $user->id);

                foreach (CarbonPeriod::create($monthStart, $monthEnd) as $day) {
                    $dateStr = $day->format('Y-m-d');

                    // Get attendance for this specific date
                    $attendance = $userAttendances->first(function ($rows) use ($dateStr) {
                        $firstRow = $rows->first();

                        return $firstRow && $firstRow->date == $dateStr;
                    });

                    if ($attendance) {
                        $attendance = $attendance->first();
                    }

                    // Check if user is on leave - FIXED: use end_date
                    $leave = $leaveRows->first(function ($lv) use ($user, $dateStr) {
                        return $lv->user_id == $user->id
                            && $dateStr >= $lv->start_date
                            && $dateStr <= $lv->end_date;
                    });

                    // Check if it's a holiday - FIXED: use end_date
                    $holiday = $holidayRows->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->end_date;
                    });

                    // Check if it's a week off
                    $weekoff = $weekoffRows->first(function ($wo) use ($user, $day) {
                        if ($wo->user_id != $user->id) {
                            return false;
                        }

                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($day->format('l'));
                        }

                        if ($wo->off_type == 'date_based') {
                            return $day->format('Y-m-d') >= $wo->start_date && $day->format('Y-m-d') <= $wo->end_date;
                        }

                        return false;
                    });

                    // Check if user has approved overtime for this date
                    $overtime = $userOvertime->first(function ($ot) use ($dateStr) {
                        return $ot->date == $dateStr;
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
                        $status = $this->getAttendanceStatusByShift($totalHours, $user->id, $dateStr, $attendance);
                        $status = strtolower($status);
                    } elseif ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
                        $status = 'checked_in_only';
                    } elseif ($holiday) {
                        $status = 'holiday';
                    } elseif ($leave) {
                        // Check if it's first half or second half leave
                        if ($leave->start_session == 1 && $leave->end_session == 1) {
                            $status = 'first_half_leave';
                        } elseif ($leave->start_session == 2 && $leave->end_session == 2) {
                            $status = 'second_half_leave';
                        } else {
                            $status = 'full_day_leave';
                        }
                    } elseif ($weekoff) {
                        $status = 'week_off';
                    }

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
                        'date' => $dateStr,
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
                        'overtime_minutes' => $overtime ? ($overtime->approved_hours * 60) : 0,
                        'overtime_hours' => $overtime ? $overtime->approved_hours : null,
                        'overtime_reason' => $overtime ? $overtime->reason : null,
                        'remarks' => $attendance->remarks ?? null,
                        'leave_type' => $leave->leave_type ?? null,
                        'holiday_name' => $holiday->name ?? null,
                        'weekoff_type' => $weekoff->off_type ?? null,
                    ];
                }
            }

            $reportCollection = collect($report);

            // ✅ Sort by date (ascending) - all 1st, then 2nd, etc.
            $reportCollection = $reportCollection->sortBy('date')->values();

            // Apply search filter (by employee name or ID)
            if ($search) {
                $reportCollection = $reportCollection->filter(function ($item) use ($search) {
                    return stripos($item['name'], $search) !== false ||
                        stripos($item['employee_id'], $search) !== false;
                });
            }

            // ✅ Apply status filter - WITH COMBINED PRESENT (Present includes halfday and checked_in_only)
            if ($statusFilter) {
                $reportCollection = $reportCollection->filter(function ($item) use ($statusFilter) {
                    $status = $item['status'];
                    switch ($statusFilter) {
                        case 'present':
                            // Present includes: present, halfday, checked_in_only
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

            if ($userIdFilter) {
                $reportCollection = $reportCollection->where('user_id', (int) $userIdFilter);
            }

            // ✅ Calculate stats from full dataset - WITH SEPARATE COUNTS
            $stats = [
                'totalEmployees' => $reportCollection->pluck('user_id')->unique()->count(),
                'presentCount' => $reportCollection->whereIn('status', ['present', 'halfday', 'checked_in_only'])->count(),
                'halfdayCount' => $reportCollection->where('status', 'halfday')->count(),
                'checkedInOnlyCount' => $reportCollection->where('status', 'checked_in_only')->count(),
                'absentCount' => $reportCollection->where('status', 'absent')->count(),
                'leaveCount' => $reportCollection->where(function ($item) {
                    return str_contains($item['status'], 'leave');
                })->count(),
                'holidayCount' => $reportCollection->where('status', 'holiday')->count(),
                'weekoffCount' => $reportCollection->where('status', 'week_off')->count(),
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
                ->get();

            $branches = DB::table('company_branches')
                ->where('tenant_id', $tenantId)->where('status', 1)
                ->select('id', 'name')->orderBy('name')->get();

            return view('client.report.attendance.attendance-detail', [
                'reportData' => new \Illuminate\Pagination\LengthAwarePaginator(
                    $paginated,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => route('report.attendance.detail.index'), 'query' => $request->query()]
                ),
                'stats' => $stats,
                'employees' => $employees,
                'branches' => $branches,
                'monthStart' => $monthStart,
                'monthEnd' => $monthEnd,
            ]);
        } catch (Exception $e) {
            Log::error('Attendance Report Error: '.$e->getMessage());
            Log::error('Trace: '.$e->getTraceAsString());

            return back()->with('error', 'Failed to generate attendance report: '.$e->getMessage());
        }
    }

    /**
     * Export Detail Attendance Report to CSV
     */
    public function detailExportAttendance(Request $request)
    {
        try {
            // Get tenant_id from session
            $tenantId = session('tenant_id');

            if (! $tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');

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
                    'cb.name as branch_name'
                )
                ->tap(fn ($q) => $this->applyReportEmployeeFilters($q, $request))
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->when($branchFilter, fn ($q) => $q->where('uj.branch_id', (int) $branchFilter))
                ->get();

            // Fetch attendance records for the month with tenant filter
            $attendanceRows = DB::table('attendances as a')
                ->where('a.tenant_id', $tenantId)
                ->whereBetween('a.date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy(function ($row) {
                    return $row->user_id.'_'.$row->date;
                });

            // Fetch approved leaves with tenant filter - FIXED: use end_date
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch holidays with tenant filter - FIXED: use end_date
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch user weekoffs with tenant filter
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get();

            // Fetch approved overtime requests for export
            $overtimeRows = DB::table('overtime_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get();

            // Build complete report data (ALL records, no pagination)
            $reportData = [];

            foreach ($users as $user) {
                // Get overtime for this user
                $userOvertime = $overtimeRows->where('user_id', $user->id);

                foreach (CarbonPeriod::create($monthStart, $monthEnd) as $day) {
                    $dateStr = $day->format('Y-m-d');
                    $key = $user->id.'_'.$dateStr;
                    $attendance = $attendanceRows->get($key);
                    $attendance = $attendance ? $attendance->first() : null;

                    // Check if user is on leave - FIXED: use end_date
                    $leave = $leaveRows->first(function ($lv) use ($user, $dateStr) {
                        return $lv->user_id == $user->id
                            && $dateStr >= $lv->start_date
                            && $dateStr <= $lv->end_date;
                    });

                    // Check if it's a holiday - FIXED: use end_date
                    $holiday = $holidayRows->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->end_date;
                    });

                    // Check if it's a week off
                    $weekoff = $weekoffRows->first(function ($wo) use ($user, $day) {
                        if ($wo->user_id != $user->id) {
                            return false;
                        }

                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($day->format('l'));
                        }

                        if ($wo->off_type == 'date_based') {
                            return $day->format('Y-m-d') >= $wo->start_date && $day->format('Y-m-d') <= $wo->end_date;
                        }

                        return false;
                    });

                    // Check if user has approved overtime for this date
                    $overtime = $userOvertime->first(function ($ot) use ($dateStr) {
                        return $ot->date == $dateStr;
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
                        $status = $this->getAttendanceStatusByShift($totalHours, $user->id, $dateStr, $attendance);
                        $status = strtolower($status);
                    } elseif ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
                        $status = 'checked_in_only';
                    } elseif ($holiday) {
                        $status = 'holiday';
                    } elseif ($leave) {
                        // Check if it's first half or second half leave
                        if ($leave->start_session == 1 && $leave->end_session == 1) {
                            $status = 'first_half_leave';
                        } elseif ($leave->start_session == 2 && $leave->end_session == 2) {
                            $status = 'second_half_leave';
                        } else {
                            $status = 'full_day_leave';
                        }
                    } elseif ($weekoff) {
                        $status = 'week_off';
                    }

                    // Build report entry with same structure as attendanceReport
                    $reportData[] = [
                        'user_id' => $user->id,
                        'employee_name' => $user->name,
                        'employee_id' => $user->employee_id ?? 'N/A',
                        'department' => $user->department_name ?? 'N/A',
                        'designation' => $user->designation_name ?? 'N/A',
                        'branch' => $user->branch_name ?? 'N/A',
                        'attendance_location' => \App\Models\AttendanceLocation::labelFor($user->office_branch, $user->location_name),
                        'date' => $dateStr,
                        'day' => Carbon::parse($dateStr)->format('D'),
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
                        'overtime_minutes' => $overtime ? ($overtime->approved_hours * 60) : 0,
                        'overtime_hours' => $overtime ? $overtime->approved_hours : null,
                        'overtime_reason' => $overtime ? $overtime->reason : null,
                        'remarks' => $attendance->remarks ?? null,
                        'leave_type' => $leave->leave_type ?? null,
                        'holiday_name' => $holiday->name ?? null,
                        'weekoff_type' => $weekoff->off_type ?? null,
                    ];
                }
            }

            // Convert to collection for filtering
            $reportCollection = collect($reportData);

            // Apply search filter (by employee name or ID)
            if ($search) {
                $reportCollection = $reportCollection->filter(function ($item) use ($search) {
                    return stripos($item['employee_name'], $search) !== false ||
                        stripos($item['employee_id'], $search) !== false;
                });
            }

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

            // Apply user filter
            if ($userIdFilter) {
                $reportCollection = $reportCollection->where('user_id', (int) $userIdFilter);
            }

            // Generate CSV
            $filename = 'attendance_report_'.Carbon::now()->format('Y-m-d_H-i').'.csv';

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

            // Data rows - use filtered collection
            $srNo = 1;
            foreach ($reportCollection as $row) {
                // ✅ Format status for display - WITH ALL STATUS TYPES
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

                // Format clock in/out
                $clockIn = $row['clock_in']
                    ? Carbon::parse($row['clock_in'])->format('d M Y h:i A')
                    : '—';
                $clockOut = $row['clock_out']
                    ? Carbon::parse($row['clock_out'])->format('d M Y h:i A')
                    : '—';

                // Format total hours - prefer worked_hours over total_hours
                $totalHoursDisplay = '—';
                if ($row['worked_hours']) {
                    $totalHoursDisplay = number_format((float) $row['worked_hours'], 2).' hrs';
                } elseif ($row['total_hours']) {
                    $totalHoursDisplay = $row['total_hours'];
                }

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
                    $totalHoursDisplay,
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

            // Reset the file pointer
            rewind($handle);

            // Get the content
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            // Return as download
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
