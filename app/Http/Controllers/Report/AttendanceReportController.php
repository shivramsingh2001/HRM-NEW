<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\SanitizesCsv;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceReportController extends Controller
{
    use SanitizesCsv;

    private function getAttendanceStatusByShift($totalHours, $userId, $date, $attendance = null)
    {
        $tenantId = session('tenant_id');
        $shiftStart = null;
        $shiftEnd = null;
        $expectedHours = null;
        
        // ============================================================
        // PRIORITY 1: Use scheduled shift times from attendance record
        // ============================================================
        if ($attendance && isset($attendance->scheduled_shift_start) && $attendance->scheduled_shift_start && 
            isset($attendance->scheduled_shift_end) && $attendance->scheduled_shift_end) {
            $shiftStart = Carbon::parse($attendance->scheduled_shift_start);
            $shiftEnd = Carbon::parse($attendance->scheduled_shift_end);
            
            // Handle overnight shifts (e.g., 22:00 to 06:00)
            if ($shiftEnd->lessThan($shiftStart)) {
                $shiftEnd->addDay();
            }
            
            $expectedHours = $shiftStart->diffInHours($shiftEnd);
        }
        
        // ============================================================
        // PRIORITY 2: If attendance doesn't have shift times, get from shifts table
        // ============================================================
        if ($expectedHours === null || $expectedHours <= 0) {
            $userShiftData = $this->getUserShiftForDate($userId, $date, $tenantId);
            
            if ($userShiftData) {
                $shift = DB::table('shifts')
                    ->where('id', $userShiftData['shift_id'])
                    ->where('tenant_id', $tenantId)
                    ->where('status', 1)
                    ->first();
                
                if ($shift) {
                    $shiftStart = Carbon::parse($shift->start_time);
                    $shiftEnd = Carbon::parse($shift->end_time);
                    
                    // Handle overnight shifts (e.g., 22:00 to 06:00)
                    if ($shiftEnd->lessThan($shiftStart)) {
                        $shiftEnd->addDay();
                    }
                    
                    $expectedHours = $shiftStart->diffInHours($shiftEnd);
                }
            }
        }
        
        // ============================================================
        // PRIORITY 3: If shift found, calculate percentage
        // ============================================================
        if ($expectedHours !== null && $expectedHours > 0 && $totalHours !== null) {
            // Calculate percentage of shift completed
            $percentage = ($totalHours / $expectedHours) * 100;
            
            // Determine status based on percentage
            // < 20% = Absent, 20-60% = Halfday, >= 60% = Present
            if ($percentage < 20) {
                return 'Absent';
            } elseif ($percentage < 60) {
                return 'Halfday';
            } else {
                return 'Present';
            }
        }
        
        // ============================================================
        // FALLBACK: Hours-based logic (No shift found anywhere)
        // ============================================================
        if ($totalHours === null || $totalHours < 2) {
            return 'Absent';
        } elseif ($totalHours < 6) {
            return 'Halfday';
        } else {
            return 'Present';
        }
    }

    /**
     * Get user's shift for a specific date
     */
    private function getUserShiftForDate($userId, $date, $tenantId)
    {
        try {
            // First check user_shifts table for specific date with tenant_id
            $userShift = DB::table('user_shifts')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('date', $date)
                ->first();
    
            if ($userShift) {
                // Check if shift exists in shifts table with tenant_id
                $shift = DB::table('shifts')
                    ->where('id', $userShift->shift_id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 1)
                    ->first();
    
                if ($shift) {
                    return [
                        'shift_id' => $shift->id,
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                        'grace_minutes' => $shift->grace_minutes ?? 0,
                        'user_shift_id' => $userShift->id
                    ];
                }
            }
    
            // If no shift in user_shifts, check user_job_details for default shift with tenant_id
            $userJobDetail = DB::table('user_job_details')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();
    
            if ($userJobDetail && $userJobDetail->shift_id) {
                // Check if shift exists in shifts table with tenant_id
                $shift = DB::table('shifts')
                    ->where('id', $userJobDetail->shift_id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 1)
                    ->first();
    
                if ($shift) {
                    return [
                        'shift_id' => $shift->id,
                        'name' => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                        'grace_minutes' => $shift->grace_minutes ?? 0,
                        'user_shift_id' => null
                    ];
                }
            }
    
            // If no shift found, return null
            return null;
        } catch (Exception $e) {
            Log::error('Error getting user shift: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper: Convert time to minutes
     */
    private function timeToMinutes($time)
    {
        if (empty($time)) return 0;

        if (strpos($time, ':') !== false) {
            $parts = explode(':', $time);
            $hours = (int)$parts[0];
            $minutes = isset($parts[1]) ? (int)$parts[1] : 0;
            return ($hours * 60) + $minutes;
        }

        return (float)$time * 60;
    }

    /**
     * Helper: Convert minutes to time format
     */
    private function minutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Display reports index page
     */
    public function index(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            return view('client.report.index');
        } catch (Exception $e) {
            Log::error('Report Index Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load reports: ' . $e->getMessage());
        }
    }

    /**
     * Detail Attendance Report (Monthly)
     */
    public function detailAttendanceReport(Request $request)
    {
        try {
            // Get tenant_id from session
            $tenantId = session('tenant_id');
           
            if (!$tenantId) {
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
    
            // Fetch all active employees (non-admin) with tenant filter
            $users = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('user_basic_details as ub', 'u.id', '=', 'ub.user_id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'u.role',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.reporting_head',
                    'ub.profile_image'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->get();
    
            // Fetch attendance records for the month with tenant filter
            $attendanceRows = DB::table('attendances as a')
                ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
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
                    'a.late_minutes',
                    'a.early_departure_minutes as earlyexitminutes',
                    'a.overtime_minutes',
                    'a.remarks',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name'
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
                    return $row->user_id . '_' . $row->date;
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
                        if ($wo->user_id != $user->id) return false;
    
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
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }
    
                    // Determine status using shift-based logic
                    $status = 'absent';
                    if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $status = $this->getAttendanceStatusByShift($totalHours, $user->id, $dateStr, $attendance);
                        $status = strtolower($status);
                    } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
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
                $reportCollection = $reportCollection->where('user_id', (int)$userIdFilter);
            }
    
            // ✅ Calculate stats from full dataset - WITH SEPARATE COUNTS
            $stats = [
                'totalEmployees' => $reportCollection->pluck('user_id')->unique()->count(),
                'presentCount' => $reportCollection->whereIn('status', ['present', 'halfday', 'checked_in_only'])->count(),
                'halfdayCount' => $reportCollection->where('status', 'halfday')->count(),
                'checkedInOnlyCount' => $reportCollection->where('status', 'checked_in_only')->count(),
                'absentCount' => $reportCollection->where('status', 'absent')->count(),
                'leaveCount' => $reportCollection->where(function($item) {
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
                'monthStart' => $monthStart,
                'monthEnd' => $monthEnd,
            ]);
        } catch (Exception $e) {
            Log::error('Attendance Report Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to generate attendance report: ' . $e->getMessage());
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
    
            if (!$tenantId) {
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
    
            // Fetch all active employees (non-admin) with tenant filter
            $users = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->get();
    
            // Fetch attendance records for the month with tenant filter
            $attendanceRows = DB::table('attendances as a')
                ->where('a.tenant_id', $tenantId)
                ->whereBetween('a.date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy(function ($row) {
                    return $row->user_id . '_' . $row->date;
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
                    $key = $user->id . '_' . $dateStr;
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
                        if ($wo->user_id != $user->id) return false;
    
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
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }
    
                    // Determine status using shift-based logic
                    $status = 'absent';
                    if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $status = $this->getAttendanceStatusByShift($totalHours, $user->id, $dateStr, $attendance);
                        $status = strtolower($status);
                    } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
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
                $reportCollection = $reportCollection->where('user_id', (int)$userIdFilter);
            }
    
            // Generate CSV
            $filename = 'attendance_report_' . Carbon::now()->format('Y-m-d_H-i') . '.csv';
    
            // Create a temporary file handle
            $handle = fopen('php://temp', 'w+');
    
            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
            // Headers
            $this->writeCsvRow($handle,[
                'SR NO.',
                'Employee Name',
                'Employee ID',
                'Department',
                'Designation',
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
                'Remarks'
            ]);
    
            // Data rows - use filtered collection
            $srNo = 1;
            foreach ($reportCollection as $row) {
                // ✅ Format status for display - WITH ALL STATUS TYPES
                $statusLabels = [
                    'present' => 'Present',
                    'absent' => 'Absent',
                    'on_leave' => 'On Leave' . ($row['leave_type'] ? ' (' . $row['leave_type'] . ')' : ''),
                    'holiday' => 'Holiday' . ($row['holiday_name'] ? ' (' . $row['holiday_name'] . ')' : ''),
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
                    $shift = $start . ' - ' . $end;
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
                    $totalHoursDisplay = number_format((float)$row['worked_hours'], 2) . ' hrs';
                } elseif ($row['total_hours']) {
                    $totalHoursDisplay = $row['total_hours'];
                }
    
                $this->writeCsvRow($handle,[
                    $srNo++,
                    $row['employee_name'],
                    $row['employee_id'],
                    $row['department'],
                    $row['designation'],
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
                    $row['overtime_hours'] ? number_format((float)$row['overtime_hours'], 2) . ' hrs' : '—',
                    $row['overtime_reason'] ?? '—',
                    $row['remarks'] ?? '—'
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
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (Exception $e) {
            Log::error('Export Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

    /**
     * Day Attendance Report (Single Day)
     */
    public function dayAttendanceReport(Request $request)
    {
        try {
            // Get tenant_id from session
            $tenantId = session('tenant_id');

            if (!$tenantId) {
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

            // Fetch all active employees (non-admin) with tenant filter
            $users = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('user_basic_details as ub', 'u.id', '=', 'ub.user_id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'u.role',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.reporting_head',
                    'ub.profile_image'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->get();

            // Apply user filter if provided
            if ($userIdFilter) {
                $users = $users->where('id', (int)$userIdFilter);
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
                    'a.late_minutes',
                    'a.early_departure_minutes as earlyexitminutes',
                    'a.overtime_minutes',
                    'a.remarks',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name'
                )
                ->where('a.tenant_id', $tenantId)
                ->where('a.date', $dayStart)
                ->get()
                ->keyBy('user_id');

            // Fetch approved leaves for this day
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($dayStart) {
                    $q->where('start_date', '<=', $dayStart)
                        ->where('start_date', '>=', $dayStart);
                })
                ->get()
                ->keyBy('user_id');

            // Fetch holidays for this day
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($dayStart) {
                    $q->where('start_date', '<=', $dayStart)
                        ->where('start_date', '>=', $dayStart);
                })
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
                    if ($wo->user_id != $user->id) return false;

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
                    $totalHours = (float)$attendance->worked_hours;
                } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);
                    $totalHours = $clockIn->diffInHours($clockOut);
                }

                // Determine status using shift-based logic
                $status = 'absent';
                if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    $status = $this->getAttendanceStatusByShift($totalHours, $user->id, $dayStart, $attendance);
                    $status = strtolower($status);
                } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                    $status = 'checked_in_only';
                } elseif ($holiday) {
                    $status = 'holiday';
                } elseif ($leave) {
                    $status = 'on_leave';
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
                    'overtime_minutes' => $overtime ? ($overtime->approved_hours * 60) : 0,
                    'overtime_hours' => $overtime ? $overtime->approved_hours : null,
                    'overtime_reason' => $overtime ? $overtime->reason : null,
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
                'selectedDate' => $dayStart,
                'selectedDateObj' => $dateObj,
                'monthStart' => $dayStart,
                'monthEnd' => $dayStart,
            ]);
        } catch (Exception $e) {
          
            Log::error('Attendance Report Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to generate attendance report: ' . $e->getMessage());
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

            if (!$tenantId) {
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

            // Fetch all active employees (non-admin) with tenant filter
            $users = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->get();

            // Apply user filter if provided
            if ($userIdFilter) {
                $users = $users->where('id', (int)$userIdFilter);
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

            // Fetch approved leaves for this day
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('start_date', '<=', $dayStart)
                ->where('start_date', '>=', $dayStart)
                ->get()
                ->keyBy('user_id');

            // Fetch holidays for this day
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where('start_date', '<=', $dayStart)
                ->where('start_date', '>=', $dayStart)
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
                    if ($wo->user_id != $user->id) return false;

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
                    $totalHours = (float)$attendance->worked_hours;
                } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);
                    $totalHours = $clockIn->diffInHours($clockOut);
                }

                // Determine status using shift-based logic
                $status = 'absent';
                if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    $status = $this->getAttendanceStatusByShift($totalHours, $user->id, $dayStart, $attendance);
                    $status = strtolower($status);
                } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                    $status = 'checked_in_only';
                } elseif ($holiday) {
                    $status = 'holiday';
                } elseif ($leave) {
                    $status = 'on_leave';
                } elseif ($weekoff) {
                    $status = 'week_off';
                }

                // ✅ Apply status filter - WITH COMBINED PRESENT
                if ($statusFilter) {
                    switch ($statusFilter) {
                        case 'present':
                            if (!in_array($status, ['present', 'halfday', 'checked_in_only'])) continue 2;
                            break;
                        case 'halfday':
                            if ($status !== 'halfday') continue 2;
                            break;
                        case 'checked_in_only':
                            if ($status !== 'checked_in_only') continue 2;
                            break;
                        case 'absent':
                            if ($status !== 'absent') continue 2;
                            break;
                        case 'on_leave':
                            if (!str_contains($status, 'leave')) continue 2;
                            break;
                        case 'holiday':
                            if ($status !== 'holiday') continue 2;
                            break;
                        case 'weekoff':
                            if ($status !== 'week_off') continue 2;
                            break;
                        default:
                            if ($status !== $statusFilter) continue 2;
                            break;
                    }
                }

                $reportData[] = [
                    'user_id' => $user->id,
                    'employee_name' => $user->name,
                    'employee_id' => $user->employee_id ?? 'N/A',
                    'department' => $user->department_name ?? 'N/A',
                    'designation' => $user->designation_name ?? 'N/A',
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
                    'overtime_minutes' => $overtime ? ($overtime->approved_hours * 60) : 0,
                    'overtime_hours' => $overtime ? $overtime->approved_hours : null,
                    'overtime_reason' => $overtime ? $overtime->reason : null,
                    'remarks' => $attendance->remarks ?? null,
                    'leave_type' => $leave->leave_type ?? null,
                    'holiday_name' => $holiday->name ?? null,
                    'weekoff_type' => $weekoff->off_type ?? null,
                ];
            }

            $reportCollection = collect($reportData);

            // Generate CSV
            $filename = 'attendance_report_' . $dayStart . '.csv';

            // Create a temporary file handle
            $handle = fopen('php://temp', 'w+');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Headers
            $this->writeCsvRow($handle,[
                'SR NO.',
                'Employee Name',
                'Employee ID',
                'Department',
                'Designation',
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
                'Remarks'
            ]);

            // Data rows
            $srNo = 1;
            foreach ($reportCollection as $row) {
                // Format status for display
                $statusLabels = [
                    'present' => 'Present',
                    'absent' => 'Absent',
                    'on_leave' => 'On Leave' . ($row['leave_type'] ? ' (' . $row['leave_type'] . ')' : ''),
                    'holiday' => 'Holiday' . ($row['holiday_name'] ? ' (' . $row['holiday_name'] . ')' : ''),
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
                    $shift = $start . ' - ' . $end;
                }

                // Format clock in/out
                $clockIn = $row['clock_in']
                    ? Carbon::parse($row['clock_in'])->format('d M Y h:i A')
                    : '—';
                $clockOut = $row['clock_out']
                    ? Carbon::parse($row['clock_out'])->format('d M Y h:i A')
                    : '—';

                $this->writeCsvRow($handle,[
                    $srNo++,
                    $row['employee_name'],
                    $row['employee_id'],
                    $row['department'],
                    $row['designation'],
                    Carbon::parse($row['date'])->format('d M Y'),
                    $row['day'],
                    $statusLabel,
                    $clockIn,
                    $clockOut,
                    $row['worked_hours'] ? number_format((float)$row['worked_hours'], 2) . ' hrs' : ($row['total_hours'] ? $row['total_hours'] : '—'),
                    $shift,
                    $row['clock_in_location'] ?? '—',
                    $row['clock_out_location'] ?? '—',
                    $row['late_minutes'] ?? 0,
                    $row['early_exit_minutes'] ?? 0,
                    $row['overtime_minutes'] ?? 0,
                    $row['overtime_hours'] ? number_format((float)$row['overtime_hours'], 2) . ' hrs' : '—',
                    $row['overtime_reason'] ?? '—',
                    $row['remarks'] ?? '—'
                ]);
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            return response($csvContent, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (Exception $e) {
            Log::error('Export Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

    /**
     * Hourly Attendance Report (Matrix)
     */
    public function hourlyAttendanceReport(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            // Get month from request - default to current month
            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');

            // Get filter values
            $search = $request->get('search');
            $departmentFilter = $request->get('department');

            // Fetch all active employees
            $employees = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.department as department_id'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->orderBy('u.name')
                ->get();

            // Apply department filter
            if ($departmentFilter) {
                $employees = $employees->where('department_id', (int)$departmentFilter);
            }

            // Apply search filter
            if ($search) {
                $employees = $employees->filter(function ($employee) use ($search) {
                    return stripos($employee->name, $search) !== false ||
                        stripos($employee->employee_id, $search) !== false ||
                        stripos($employee->email, $search) !== false;
                });
            }

            // Fetch attendance records for the month
            $attendances = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy('user_id');

            // Fetch approved leaves for the month
            $leaves = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get()
                ->groupBy('user_id');

            // Fetch holidays
            $holidays = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch user weekoffs
            $weekoffs = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get()
                ->groupBy('user_id');

            // Build report data
            $reportData = [];
            $daysInMonth = $selectedDate->daysInMonth;
            $dayNames = [];

            // Get day names for the month
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                $dayNames[$i] = $date->format('D');
            }

            $totalHoursSum = 0;

            foreach ($employees as $employee) {
                $employeeAttendances = $attendances->get($employee->id) ?? collect();
                $employeeLeaves = $leaves->get($employee->id) ?? collect();
                $employeeWeekoffs = $weekoffs->get($employee->id) ?? collect();

                $row = [
                    'employee_id' => $employee->employee_id ?? '--',
                    'name' => $employee->name,
                    'designation' => $employee->designation_name ?? '--',
                    'department' => $employee->department_name ?? '--',
                    'days' => [],
                    'total_hours' => '00:00',
                    'total_hours_decimal' => 0,
                    'present_days' => 0,
                    'absent_days' => 0,
                    'leave_days' => 0,
                    'weekoff_days' => 0,
                ];

                $totalMinutes = 0;

                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                    $dateStr = $date->format('Y-m-d');

                    // Check for attendance
                    $attendance = $employeeAttendances->first(function ($att) use ($dateStr) {
                        return $att->date == $dateStr;
                    });

                    // Check for leave
                    $leave = $employeeLeaves->first(function ($lv) use ($dateStr) {
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->start_date;
                    });

                    // Check for holiday
                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->start_date;
                    });

                    // Check for weekoff
                    $weekoff = $employeeWeekoffs->first(function ($wo) use ($date) {
                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($date->format('l'));
                        }
                        if ($wo->off_type == 'date_based') {
                            return $date->format('Y-m-d') >= $wo->start_date &&
                                $date->format('Y-m-d') <= $wo->end_date;
                        }
                        return false;
                    });

                    // Calculate total hours from worked_hours
                    $totalHours = null;
                    if ($attendance && $attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }

                    // Determine status using shift-based logic
                    $status = 'absent';
                    $cellValue = '--';
                    
                    if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $status = $this->getAttendanceStatusByShift($totalHours, $employee->id, $dateStr, $attendance);
                        if ($status === 'Present') {
                            $cellValue = $attendance->total_hours ?? '--';
                            $totalMinutes += $this->timeToMinutes($attendance->total_hours ?? '00:00');
                            $row['present_days']++;
                        } elseif ($status === 'Halfday') {
                            $cellValue = 'Halfday';
                            $totalMinutes += $this->timeToMinutes($attendance->total_hours ?? '00:00');
                            $row['present_days']++;
                        }
                    } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                        $status = 'Checked In Only';
                        $cellValue = 'C/I';
                        $row['present_days']++;
                    } elseif ($holiday) {
                        $status = 'Holiday';
                        $cellValue = 'H';
                    } elseif ($leave) {
                        $status = 'Leave';
                        $cellValue = 'L';
                        $row['leave_days']++;
                    } elseif ($weekoff) {
                        $status = 'Week Off';
                        $cellValue = 'WO';
                        $row['weekoff_days']++;
                    } else {
                        $status = 'Absent';
                        $cellValue = 'A';
                        $row['absent_days']++;
                    }

                    $row['days'][$i] = [
                        'value' => $cellValue,
                        'status' => $status,
                        'date' => $dateStr,
                        'day' => $date->format('D'),
                    ];
                }

                // Calculate total hours
                $row['total_hours'] = $this->minutesToTime($totalMinutes);
                $row['total_hours_decimal'] = round($totalMinutes / 60, 2);
                $totalHoursSum += $totalMinutes;

                $reportData[] = $row;
            }

            // Calculate summary statistics
            $stats = [
                'total_employees' => $employees->count(),
                'total_present' => collect($reportData)->sum('present_days'),
                'total_absent' => collect($reportData)->sum('absent_days'),
                'total_leave' => collect($reportData)->sum('leave_days'),
                'total_weekoff' => collect($reportData)->sum('weekoff_days'),
                'total_hours' => $this->minutesToTime($totalHoursSum),
            ];

            // Get departments for filter
            $departments = DB::table('departments')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            // Paginate the report data
            $perPage = $request->get('per_page', 50);
            $currentPage = $request->get('page', 1);
            $total = count($reportData);
            $paginatedData = array_slice($reportData, ($currentPage - 1) * $perPage, $perPage);

            return view('client.report.attendance.attendance-hourly', [
                'reportData' => $paginatedData,
                'allData' => $reportData,
                'dayNames' => $dayNames,
                'daysInMonth' => $daysInMonth,
                'selectedMonth' => $month,
                'selectedDate' => $selectedDate,
                'stats' => $stats,
                'employees' => $employees,
                'departments' => $departments,
                'paginator' => new \Illuminate\Pagination\LengthAwarePaginator(
                    $paginatedData,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => route('report.attendance.hourly.index'), 'query' => $request->query()]
                ),
            ]);
        } catch (Exception $e) {
           
            Log::error('Attendance Matrix Report Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * Export Hourly Attendance Report to CSV
     */
    public function hourlyExportAttendance(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $daysInMonth = $selectedDate->daysInMonth;

            // Fetch employees with filters
            $employees = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.department as department_id'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->orderBy('u.name')
                ->get();

            // Apply filters
            if ($request->get('department')) {
                $employees = $employees->where('department_id', (int)$request->get('department'));
            }

            if ($request->get('search')) {
                $search = $request->get('search');
                $employees = $employees->filter(function ($employee) use ($search) {
                    return stripos($employee->name, $search) !== false ||
                        stripos($employee->employee_id, $search) !== false;
                });
            }

            // Fetch attendance and other data (same as index method)
            $attendances = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy('user_id');

            $leaves = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get()
                ->groupBy('user_id');

            $holidays = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get();

            $weekoffs = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get()
                ->groupBy('user_id');

            // Build CSV data
            $csvData = [];
            
            // Get tenant details
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();

            // Add header information
            $csvData[] = ['Firm Name: ' . ($tenant->company_name ?? 'Company Name')];
            $csvData[] = ['Attendance Report'];
            $csvData[] = ['Month: ' . $selectedDate->format('F, Y')];
            $csvData[] = [];

            // Add header row
            $header = ['S.No', 'Emp Code', 'Name', 'Designation', 'Department'];
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                $header[] =  $date->format('d-m-y') . ' (' . $date->format('D') . ')';
            }
            $header[] = 'Total Hours';
            $csvData[] = $header;

            $rowNumber = 1;
            foreach ($employees as $employee) {
                $employeeAttendances = $attendances->get($employee->id) ?? collect();
                $employeeLeaves = $leaves->get($employee->id) ?? collect();
                $employeeWeekoffs = $weekoffs->get($employee->id) ?? collect();

                $row = [
                    $rowNumber++,
                    $employee->employee_id ?? '--',
                    $employee->name,
                    $employee->designation_name ?? '--',
                    $employee->department_name ?? '--'
                ];

                $totalMinutes = 0;

                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                    $dateStr = $date->format('Y-m-d');

                    $attendance = $employeeAttendances->first(function ($att) use ($dateStr) {
                        return $att->date == $dateStr;
                    });

                    $leave = $employeeLeaves->first(function ($lv) use ($dateStr) {
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->start_date;
                    });

                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->start_date;
                    });

                    $weekoff = $employeeWeekoffs->first(function ($wo) use ($date) {
                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($date->format('l'));
                        }
                        if ($wo->off_type == 'date_based') {
                            return $date->format('Y-m-d') >= $wo->start_date &&
                                $date->format('Y-m-d') <= $wo->start_date;
                        }
                        return false;
                    });

                    // Calculate total hours from worked_hours
                    $totalHours = null;
                    if ($attendance && $attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }

                    // Determine status using shift-based logic
                    $cellValue = '--';
                    
                    if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $status = $this->getAttendanceStatusByShift($totalHours, $employee->id, $dateStr, $attendance);
                        if ($status === 'Present') {
                            $cellValue = $attendance->total_hours ?? '--';
                            $totalMinutes += $this->timeToMinutes($attendance->total_hours ?? '00:00');
                        } elseif ($status === 'Halfday') {
                            $cellValue = 'Halfday';
                            $totalMinutes += $this->timeToMinutes($attendance->total_hours ?? '00:00');
                        }
                    } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                        $cellValue = 'C/I';
                    } elseif ($holiday) {
                        $cellValue = 'H';
                    } elseif ($leave) {
                        $cellValue = 'L';
                    } elseif ($weekoff) {
                        $cellValue = 'WO';
                    } else {
                        $cellValue = 'A';
                    }

                    $row[] = $cellValue;
                }

                $row[] = $this->minutesToTime($totalMinutes);
                $csvData[] = $row;
            }

            // Generate CSV
            $filename = 'Attendance_Report_' . $selectedDate->format('F_Y') . '.csv';

            $handle = fopen('php://temp', 'w+');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            foreach ($csvData as $row) {
                $this->writeCsvRow($handle,$row);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (Exception $e) {
            Log::error('Attendance Matrix Export Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

    /**
     * Overall Attendance Report
     */
    public function overallAttendanceReport(Request $request)
    {
        try {
            $tenantId = session('tenant_id');
    
            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }
    
            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $daysInMonth = $selectedDate->daysInMonth;
    
            // Get filter values
            $search = $request->get('search');
            $departmentFilter = $request->get('department');
    
            // Fetch employees with details
            $employees = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.department as department_id'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->orderBy('u.name')
                ->get();
    
            // Apply department filter
            if ($departmentFilter) {
                $employees = $employees->where('department_id', (int)$departmentFilter);
            }
    
            // Apply search filter
            if ($search) {
                $employees = $employees->filter(function ($employee) use ($search) {
                    return stripos($employee->name, $search) !== false ||
                        stripos($employee->employee_id, $search) !== false;
                });
            }
    
            // Fetch attendance records
            $attendances = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy('user_id');
    
            // Fetch approved leaves
            $leaves = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get()
                ->groupBy('user_id');
    
            // Fetch holidays
            $holidays = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get();
    
            // Fetch user weekoffs
            $weekoffs = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get()
                ->groupBy('user_id');
    
            // Build report data
            $reportData = [];
            $dateLabels = [];
    
            // Get date labels for the month
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                $dateLabels[$i] = $date->format('Y-m-d');
            }
    
            foreach ($employees as $employee) {
                $employeeAttendances = $attendances->get($employee->id) ?? collect();
                $employeeLeaves = $leaves->get($employee->id) ?? collect();
                $employeeWeekoffs = $weekoffs->get($employee->id) ?? collect();
    
                $row = [
                    'employee_name' => $employee->name,
                    'employee_id' => $employee->employee_id ?? '--',
                    'designation' => $employee->designation_name ?? '--',
                    'department' => $employee->department_name ?? '--',
                    'days' => [],
                    'total_present' => 0,
                    'total_absent' => 0,
                    'total_leave' => 0,
                    'total_halfday' => 0,
                    'total_holiday' => 0,
                    'total_weekoff' => 0,
                ];
    
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                    $dateStr = $date->format('Y-m-d');
    
                    // Check for attendance
                    $attendance = $employeeAttendances->first(function ($att) use ($dateStr) {
                        return $att->date == $dateStr;
                    });
    
                    // Check for leave
                    $leave = $employeeLeaves->first(function ($lv) use ($dateStr) {
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->start_date;
                    });
    
                    // Check for holiday
                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->start_date;
                    });
    
                    // Check for weekoff
                    $weekoff = $employeeWeekoffs->first(function ($wo) use ($date) {
                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($date->format('l'));
                        }
                        if ($wo->off_type == 'date_based') {
                            return $date->format('Y-m-d') >= $wo->start_date &&
                                $date->format('Y-m-d') <= $wo->end_date;
                        }
                        return false;
                    });
    
                    // Calculate total hours from worked_hours
                    $totalHours = null;
                    if ($attendance && $attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }
    
                    // Determine status using shift-based logic
                    $status = 'Absent';
                    
                    if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $status = $this->getAttendanceStatusByShift($totalHours, $employee->id, $dateStr, $attendance);
                        if ($status === 'Present') {
                            $row['total_present']++;
                        } elseif ($status === 'Halfday') {
                            $row['total_halfday']++;
                            // ✅ FIX: Add 0.5 for present and 0.5 for absent
                            $row['total_present'] += 0.5;
                            $row['total_absent'] += 0.5;
                        }
                    } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                        $status = 'P';
                        $row['total_present']++;
                    } elseif ($holiday) {
                        $status = 'Holiday';
                        $row['total_holiday']++;
                    } elseif ($leave) {
                        $status = 'Leave';
                        $row['total_leave']++;
                    } elseif ($weekoff) {
                        $status = 'WeekOff';
                        $row['total_weekoff']++;
                    } else {
                        $status = 'Absent';
                        $row['total_absent']++;
                    }
    
                    // Store the display status
                    $displayStatus = match ($status) {
                        'Present' => 'P',
                        'Halfday' => 'Halfday',
                        default => $status
                    };
                    
                    $row['days'][$i] = $displayStatus;
                }
    
                $reportData[] = $row;
            }
    
            // Calculate summary statistics
            $stats = [
                'total_employees' => count($reportData),
                'total_present' => number_format(collect($reportData)->sum('total_present'), 2),
                'total_absent' => number_format(collect($reportData)->sum('total_absent'), 2),
                'total_leave' => collect($reportData)->sum('total_leave'),
                'total_halfday' => collect($reportData)->sum('total_halfday'),
                'total_holiday' => collect($reportData)->sum('total_holiday'),
                'total_weekoff' => collect($reportData)->sum('total_weekoff'),
            ];
    
            // Get departments for filter
            $departments = DB::table('departments')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
    
            // Paginate the report data
            $perPage = $request->get('per_page', 50);
            $currentPage = $request->get('page', 1);
            $total = count($reportData);
            $paginatedData = array_slice($reportData, ($currentPage - 1) * $perPage, $perPage);
    
            return view('client.report.attendance.attendance-overall', [
                'reportData' => $paginatedData,
                'allData' => $reportData,
                'dateLabels' => $dateLabels,
                'daysInMonth' => $daysInMonth,
                'selectedMonth' => $month,
                'selectedDate' => $selectedDate,
                'stats' => $stats,
                'departments' => $departments,
                'paginator' => new \Illuminate\Pagination\LengthAwarePaginator(
                    $paginatedData,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => route('report.attendance.overall.index'), 'query' => $request->query()]
                ),
            ]);
        } catch (Exception $e) {
            Log::error('Overall Attendance Report Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * Export Overall Attendance Report to CSV
     */
    public function overallExportAttendance(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $daysInMonth = $selectedDate->daysInMonth;

            // Get tenant details
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();

            // Fetch employees with filters
            $employees = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.department as department_id'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->orderBy('u.name')
                ->get();

            // Apply filters
            if ($request->get('department')) {
                $employees = $employees->where('department_id', (int)$request->get('department'));
            }

            if ($request->get('search')) {
                $search = $request->get('search');
                $employees = $employees->filter(function ($employee) use ($search) {
                    return stripos($employee->name, $search) !== false ||
                        stripos($employee->employee_id, $search) !== false;
                });
            }

            // Fetch attendance and other data
            $attendances = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy('user_id');

            $leaves = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get()
                ->groupBy('user_id');

            $holidays = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get();

            $weekoffs = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get()
                ->groupBy('user_id');

            // Build CSV data
            $csvData = [];

            // Add header information
            $csvData[] = ['Firm Name: ' . ($tenant->company_name ?? 'Company Name')];
            $csvData[] = ['Attendance Report'];
            $csvData[] = ['Month: ' . $selectedDate->format('F, Y')];
            $csvData[] = [];

            // Build header row
            $header = ['Employee ID', 'Employee Name', 'Designation', 'Department'];
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                $header[] = $date->format('Y-m-d');
            }
            $header[] = 'Total Present';
            $header[] = 'Total Absent';
            $header[] = 'Total Leave';
            $header[] = 'Total Halfday';
            $header[] = 'Total Holiday';
            $header[] = 'Total WeekOff';
            $csvData[] = $header;

            foreach ($employees as $employee) {
                $employeeAttendances = $attendances->get($employee->id) ?? collect();
                $employeeLeaves = $leaves->get($employee->id) ?? collect();
                $employeeWeekoffs = $weekoffs->get($employee->id) ?? collect();

                $row = [
                    $employee->employee_id ?? '--',
                    $employee->name,
                    $employee->designation_name ?? '--',
                    $employee->department_name ?? '--'
                ];

                $totalPresent = 0;
                $totalAbsent = 0;
                $totalLeave = 0;
                $totalHalfday = 0;
                $totalHoliday = 0;
                $totalWeekoff = 0;

                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                    $dateStr = $date->format('Y-m-d');

                    $attendance = $employeeAttendances->first(function ($att) use ($dateStr) {
                        return $att->date == $dateStr;
                    });

                    $leave = $employeeLeaves->first(function ($lv) use ($dateStr) {
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->start_date;
                    });

                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->start_date;
                    });

                    $weekoff = $employeeWeekoffs->first(function ($wo) use ($date) {
                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($date->format('l'));
                        }
                        if ($wo->off_type == 'date_based') {
                            return $date->format('Y-m-d') >= $wo->start_date &&
                                $date->format('Y-m-d') <= $wo->end_date;
                        }
                        return false;
                    });

                    // Calculate total hours from worked_hours
                    $totalHours = null;
                    if ($attendance && $attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }

                    // Determine status using shift-based logic
                    $status = 'Absent';
                    
                    if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $status = $this->getAttendanceStatusByShift($totalHours, $employee->id, $dateStr, $attendance);
                        if ($status === 'Present') {
                            $row[] = 'P';
                            $totalPresent++;
                        } elseif ($status === 'Halfday') {
                            $row[] = 'HD';
                            $totalHalfday++;
                            // ✅ FIX: Add 0.5 for present and 0.5 for absent
                            $totalPresent += 0.5;
                            $totalAbsent += 0.5;
                        }
                    } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                        $row[] = 'P';
                        $totalPresent++;
                    } elseif ($holiday) {
                        $row[] = 'Holiday';
                        $totalHoliday++;
                    } elseif ($leave) {
                        $row[] = 'Leave';
                        $totalLeave++;
                    } elseif ($weekoff) {
                        $row[] = 'WeekOff';
                        $totalWeekoff++;
                    } else {
                        $row[] = 'Absent';
                        $totalAbsent++;
                    }
                }

                $row[] = $totalPresent;
                $row[] = $totalAbsent;
                $row[] = $totalLeave;
                $row[] = $totalHalfday;
                $row[] = $totalHoliday;
                $row[] = $totalWeekoff;

                $csvData[] = $row;
            }

            // Generate CSV
            $filename = 'Attendance_Overall_Report_' . $selectedDate->format('F_Y') . '.csv';

            $handle = fopen('php://temp', 'w+');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            foreach ($csvData as $row) {
                $this->writeCsvRow($handle,$row);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (Exception $e) {
            Log::error('Attendance Export Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

    /**
     * Display employee-wise detailed attendance report
     */
    public function employeeWiseAttendance(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $daysInMonth = $selectedDate->daysInMonth;

            // Get filter values
            $search = $request->get('search');
            $departmentFilter = $request->get('department');
            $employeeIdFilter = $request->get('employee_id');

            // Fetch all employees with details
            $employeesQuery = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.department as department_id'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->orderBy('u.name');

            // Apply filters
            if ($employeeIdFilter) {
                $employeesQuery->where('u.id', (int)$employeeIdFilter);
            }

            if ($departmentFilter) {
                $employeesQuery->where('uj.department', (int)$departmentFilter);
            }

            if ($search) {
                $employeesQuery->where(function ($q) use ($search) {
                    $q->where('u.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('u.employee_id', 'LIKE', '%' . $search . '%');
                });
            }

            $employees = $employeesQuery->get();

            // Get all employees for dropdown
            $allEmployees = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->where('role', '!=', 'admin')
                ->select('id', 'name', 'employee_id')
                ->orderBy('name')
                ->get();

            // Get departments for filter
            $departments = DB::table('departments')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            // If no employees found
            if ($employees->isEmpty()) {
                return view('client.report.attendance.attendance-employee-wise', [
                    'employees' => $employees,
                    'allEmployees' => $allEmployees,
                    'departments' => $departments,
                    'selectedMonth' => $month,
                    'selectedDate' => $selectedDate,
                    'employeeData' => [],
                    'summary' => [],
                    'daysInMonth' => $daysInMonth,
                    'monthStart' => $monthStart,
                    'employee' => null,
                    'dailyData' => [],
                ]);
            }

            // If single employee selected, show detail view
            if ($employeeIdFilter && $employees->count() == 1) {
                $employee = $employees->first();

                // Fetch attendance records for this employee
                $attendances = DB::table('attendances')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $employee->id)
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->get()
                    ->keyBy('date');

                // Fetch approved leaves
                $leaves = DB::table('leaves')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $employee->id)
                    ->where('status', 'approved')
                    ->where(function ($q) use ($monthStart, $monthEnd) {
                        $q->whereBetween('start_date', [$monthStart, $monthEnd])
                            ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                            ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                                $q2->where('start_date', '<=', $monthStart)
                                    ->where('start_date', '>=', $monthEnd);
                            });
                    })
                    ->get();

                // Fetch holidays
                $holidays = DB::table('holidays')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($monthStart, $monthEnd) {
                        $q->whereBetween('start_date', [$monthStart, $monthEnd])
                            ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                            ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                                $q2->where('start_date', '<=', $monthStart)
                                    ->where('start_date', '>=', $monthEnd);
                            });
                    })
                    ->get();

                // Fetch user weekoffs
                $weekoffs = DB::table('user_weekoffs')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $employee->id)
                    ->where('status', 1)
                    ->get();

                // Build daily data
                $dailyData = [];
                $summary = [
                    'present' => 0,
                    'holiday' => 0,
                    'weekoff' => 0,
                    'halfday' => 0,
                    'absent' => 0,
                    'paid_days' => 0,
                    'work_hours' => 0,
                    'short_hours' => 0,
                    'ot_hours' => 0,
                ];

                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                    $dateStr = $date->format('Y-m-d');

                    $attendance = $attendances->get($dateStr);
                    $leave = $leaves->first(function ($lv) use ($dateStr) {
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->start_date;
                    });
                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->start_date;
                    });
                    $weekoff = $weekoffs->first(function ($wo) use ($date) {
                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($date->format('l'));
                        }
                        if ($wo->off_type == 'date_based') {
                            return $date->format('Y-m-d') >= $wo->start_date &&
                                $date->format('Y-m-d') <= $wo->end_date;
                        }
                        return false;
                    });

                    $dayData = [
                        'label' => $i,
                        'in_time' => '--',
                        'out_time' => '--',
                        'shift_from' => '--',
                        'shift_to' => '--',
                        'working' => '--',
                        'short_hrs' => '--',
                        'ot_times' => '--',
                        'status' => '--',
                    ];

                    // Calculate total hours from worked_hours
                    $totalHours = null;
                    if ($attendance && $attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }

                    if ($attendance) {
                        $dayData['in_time'] = $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('h:i A') : '--';
                        $dayData['out_time'] = $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('h:i A') : '--';

                        if ($attendance->scheduled_shift_start) {
                            $dayData['shift_from'] = Carbon::parse($attendance->scheduled_shift_start)->format('h:i A');
                        }
                        if ($attendance->scheduled_shift_end) {
                            $dayData['shift_to'] = Carbon::parse($attendance->scheduled_shift_end)->format('h:i A');
                        }

                        if ($attendance->clock_in && $attendance->clock_out) {
                            $status = $this->getAttendanceStatusByShift($totalHours, $employee->id, $dateStr, $attendance);
                            
                            if ($status === 'Present') {
                                $dayData['status'] = 'P';
                                $summary['present']++;
                            } elseif ($status === 'Halfday') {
                                $dayData['status'] = 'HD';
                                $summary['halfday']++;
                                $summary['present']++;
                            } else {
                                $dayData['status'] = 'A';
                                $summary['absent']++;
                            }

                            $workingMinutes = $this->timeToMinutes($attendance->total_hours ?? '00:00');
                            $dayData['working'] = $attendance->total_hours ?? '00:00';
                            $summary['work_hours'] += $workingMinutes;

                            // Short hours (less than 8 hours)
                            $shortMinutes = max(0, 480 - $workingMinutes);
                            if ($shortMinutes > 0) {
                                $dayData['short_hrs'] = $this->minutesToTime($shortMinutes);
                                $summary['short_hours'] += $shortMinutes;
                            }

                            // Overtime (more than 8 hours)
                            $otMinutes = max(0, $workingMinutes - 480);
                            if ($otMinutes > 0) {
                                $dayData['ot_times'] = $this->minutesToTime($otMinutes);
                                $summary['ot_hours'] += $otMinutes;
                            }
                        } elseif ($attendance->clock_in && !$attendance->clock_out) {
                            $dayData['status'] = 'CI';
                            $summary['present']++;
                        }
                    } elseif ($holiday) {
                        $dayData['status'] = 'H';
                        $summary['holiday']++;
                    } elseif ($leave) {
                        $dayData['status'] = 'L';
                        $summary['paid_days']++;
                    } elseif ($weekoff) {
                        $dayData['status'] = 'WO';
                        $summary['weekoff']++;
                    } else {
                        $dayData['status'] = 'A';
                        $summary['absent']++;
                    }

                    $dailyData[$i] = $dayData;
                }

                return view('client.report.attendance.attendance-employee-wise', [
                    'employee' => $employee,
                    'dailyData' => $dailyData,
                    'daysInMonth' => $daysInMonth,
                    'selectedMonth' => $month,
                    'selectedDate' => $selectedDate,
                    'summary' => $summary,
                    'departments' => $departments,
                    'allEmployees' => $allEmployees,
                    'monthStart' => $monthStart,
                    'employeeData' => [],
                    'employees' => $employees,
                ]);
            }

            // Show all employees summary view
            $employeeData = [];

            foreach ($employees as $employee) {
                // Fetch attendance records for this employee
                $attendances = DB::table('attendances')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $employee->id)
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->get()
                    ->keyBy('date');

                // Fetch approved leaves
                $leaves = DB::table('leaves')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $employee->id)
                    ->where('status', 'approved')
                    ->where(function ($q) use ($monthStart, $monthEnd) {
                        $q->whereBetween('start_date', [$monthStart, $monthEnd])
                            ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                            ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                                $q2->where('start_date', '<=', $monthStart)
                                    ->where('start_date', '>=', $monthEnd);
                            });
                    })
                    ->get();

                // Fetch holidays
                $holidays = DB::table('holidays')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($monthStart, $monthEnd) {
                        $q->whereBetween('start_date', [$monthStart, $monthEnd])
                            ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                            ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                                $q2->where('start_date', '<=', $monthStart)
                                    ->where('start_date', '>=', $monthEnd);
                            });
                    })
                    ->get();

                // Fetch user weekoffs
                $weekoffs = DB::table('user_weekoffs')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $employee->id)
                    ->where('status', 1)
                    ->get();

                $dailyData = [];
                $summary = [
                    'present' => 0,
                    'holiday' => 0,
                    'weekoff' => 0,
                    'halfday' => 0,
                    'absent' => 0,
                    'paid_days' => 0,
                    'work_hours' => 0,
                    'short_hours' => 0,
                    'ot_hours' => 0,
                ];

                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                    $dateStr = $date->format('Y-m-d');

                    $attendance = $attendances->get($dateStr);
                    $leave = $leaves->first(function ($lv) use ($dateStr) {
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->start_date;
                    });
                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->start_date;
                    });
                    $weekoff = $weekoffs->first(function ($wo) use ($date) {
                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($date->format('l'));
                        }
                        if ($wo->off_type == 'date_based') {
                            return $date->format('Y-m-d') >= $wo->start_date &&
                                $date->format('Y-m-d') <= $wo->end_date;
                        }
                        return false;
                    });

                    // Calculate total hours from worked_hours
                    $totalHours = null;
                    if ($attendance && $attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }

                    $status = '--';

                    if ($attendance) {
                        if ($attendance->clock_in && $attendance->clock_out) {
                            $status = $this->getAttendanceStatusByShift($totalHours, $employee->id, $dateStr, $attendance);
                            
                            if ($status === 'Present') {
                                $summary['present']++;
                            } elseif ($status === 'Halfday') {
                                $summary['halfday']++;
                                $summary['present']++;
                            }

                            $workingMinutes = $this->timeToMinutes($attendance->total_hours ?? '00:00');
                            $summary['work_hours'] += $workingMinutes;

                            $shortMinutes = max(0, 480 - $workingMinutes);
                            if ($shortMinutes > 0) {
                                $summary['short_hours'] += $shortMinutes;
                            }

                            $otMinutes = max(0, $workingMinutes - 480);
                            if ($otMinutes > 0) {
                                $summary['ot_hours'] += $otMinutes;
                            }
                        } elseif ($attendance->clock_in && !$attendance->clock_out) {
                            $status = 'CI';
                            $summary['present']++;
                        }
                    } elseif ($holiday) {
                        $status = 'H';
                        $summary['holiday']++;
                    } elseif ($leave) {
                        $status = 'L';
                        $summary['paid_days']++;
                    } elseif ($weekoff) {
                        $status = 'WO';
                        $summary['weekoff']++;
                    } else {
                        $status = 'A';
                        $summary['absent']++;
                    }

                    $dailyData[$i] = $status;
                }

                $employeeData[] = [
                    'employee' => $employee,
                    'dailyData' => $dailyData,
                    'summary' => $summary,
                ];
            }

            return view('client.report.attendance.attendance-employee-wise', [
                'employeeData' => $employeeData,
                'employees' => $employees,
                'allEmployees' => $allEmployees,
                'departments' => $departments,
                'selectedMonth' => $month,
                'selectedDate' => $selectedDate,
                'daysInMonth' => $daysInMonth,
                'monthStart' => $monthStart,
                'employee' => null,
                'dailyData' => [],
                'summary' => [],
            ]);
        } catch (Exception $e) {
            Log::error('Employee Wise Attendance Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
        }
    }
    
    
    public function branchWiseAttendanceReport(Request $request)
    {
        try {
            $tenantId = session('tenant_id');
    
            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }
    
            // Get date from request - default to today
            $selectedDate = $request->get('date', now()->format('Y-m-d'));
            $dateObj = Carbon::parse($selectedDate);
            $dateStr = $dateObj->format('Y-m-d');
    
            // Fetch all active branches with tenant filter
            $branches = DB::table('branches')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get();
    
            $branchStats = [];
    
            foreach ($branches as $branch) {
                // Get employees for this branch using office_branch field
                $employeeIds = DB::table('users as u')
                    ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                    ->where('u.tenant_id', $tenantId)
                    ->where('u.status', 1)
                    ->where('u.role', '!=', 'admin')
                    ->where('uj.office_branch', $branch->id)
                    ->pluck('u.id')
                    ->toArray();
    
                $branchStats[$branch->id] = [
                    'branch' => $branch,
                    'total_employees' => count($employeeIds),
                    'present' => 0,
                    'halfday' => 0,
                    'absent' => 0,
                    'on_leave' => 0,
                    'holiday' => 0,
                    'week_off' => 0,
                ];
    
                if (!empty($employeeIds)) {
                    // Fetch attendance for the specific date
                    $attendances = DB::table('attendances')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('user_id', $employeeIds)
                        ->where('date', $dateStr)
                        ->get()
                        ->groupBy('user_id');
    
                    // Fetch approved leaves for the specific date
                    $leaves = DB::table('leaves')
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'approved')
                        ->whereIn('user_id', $employeeIds)
                        ->where('start_date', '<=', $dateStr)
                        ->where('end_date', '>=', $dateStr)
                        ->get()
                        ->keyBy('user_id');
    
                    // Fetch holidays for the specific date
                    $holidays = DB::table('holidays')
                        ->where('tenant_id', $tenantId)
                        ->where('start_date', '<=', $dateStr)
                        ->where('end_date', '>=', $dateStr)
                        ->get();
    
                    // Fetch user weekoffs
                    $weekoffs = DB::table('user_weekoffs')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('user_id', $employeeIds)
                        ->where('status', 1)
                        ->get()
                        ->groupBy('user_id');
    
                    // Calculate stats for each employee for the specific date
                    foreach ($employeeIds as $employeeId) {
                        // Check if ANY attendance record has clock_in for this employee
                        $attendanceRecords = $attendances->get($employeeId);
                        $hasClockIn = false;
                        $attendance = null;
                        $totalHours = null;
                        
                        if ($attendanceRecords) {
                            foreach ($attendanceRecords as $record) {
                                if (!is_null($record->clock_in)) {
                                    $hasClockIn = true;
                                    $attendance = $record;
                                    // Calculate total hours from worked_hours
                                    if ($attendance->worked_hours) {
                                        $totalHours = (float)$attendance->worked_hours;
                                    } elseif ($attendance->clock_in && $attendance->clock_out) {
                                        $clockIn = Carbon::parse($attendance->clock_in);
                                        $clockOut = Carbon::parse($attendance->clock_out);
                                        $totalHours = $clockIn->diffInHours($clockOut);
                                    }
                                    break;
                                }
                            }
                        }
    
                        $leave = $leaves->get($employeeId);
                        $holiday = $holidays->first();
                        $weekoff = $weekoffs->get($employeeId)?->first(function ($wo) use ($dateObj) {
                            if ($wo->off_type == 'day_based') {
                                return strtolower($wo->day_name) == strtolower($dateObj->format('l'));
                            }
                            if ($wo->off_type == 'date_based') {
                                return $dateObj->format('Y-m-d') >= $wo->start_date &&
                                       $dateObj->format('Y-m-d') <= $wo->end_date;
                            }
                            return false;
                        });
    
                        // Determine status with half-day support using the same logic as other reports
                        $status = $this->determineUserStatus(
                            $attendance,
                            $leave,
                            $holiday,
                            $weekoff,
                            $dateStr,
                            $employeeId
                        );
    
                        // Count attendance based on status
                        if ($status === 'present' || $status === 'Present') {
                            $branchStats[$branch->id]['present']++;
                        } elseif ($status === 'halfday' || $status === 'Halfday') {
                            $branchStats[$branch->id]['halfday']++;
                            // Count halfday as present for summary
                            $branchStats[$branch->id]['present']++;
                        } elseif ($status === 'holiday' || $status === 'Holiday') {
                            $branchStats[$branch->id]['holiday']++;
                        } elseif ($status === 'on_leave' || str_contains($status, 'leave')) {
                            $branchStats[$branch->id]['on_leave']++;
                        } elseif ($status === 'week_off' || $status === 'Week Off') {
                            $branchStats[$branch->id]['week_off']++;
                        } elseif ($status === 'checked_in_only') {
                            $branchStats[$branch->id]['present']++;
                        } else {
                            $branchStats[$branch->id]['absent']++;
                        }
                    }
                }
            }
    
            // Calculate overall stats
            $overallStats = [
                'total_branches' => $branches->count(),
                'total_employees' => collect($branchStats)->sum('total_employees'),
                'total_present' => collect($branchStats)->sum('present'),
                'total_halfday' => collect($branchStats)->sum('halfday'),
                'total_absent' => collect($branchStats)->sum('absent'),
                'total_leave' => collect($branchStats)->sum('on_leave'),
                'total_holiday' => collect($branchStats)->sum('holiday'),
                'total_weekoff' => collect($branchStats)->sum('week_off'),
            ];
         
            return view('client.report.attendance.attendance-branch-wise', [
                'branchStats' => $branchStats,
                'stats' => $overallStats,
                'selectedDate' => $dateStr,
                'dateObj' => $dateObj,
            ]);
        } catch (Exception $e) {
            Log::error('Branch Wise Attendance Report Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to generate branch-wise attendance report: ' . $e->getMessage());
        }
    }

    /**
     * Branch Wise Detail Report - View employees of a specific branch
     */
    public function branchWiseDetailReport(Request $request, $branchId)
    {
        try {
            $tenantId = session('tenant_id');
    
            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }
    
            // Get branch details
            $branch = DB::table('branches')
                ->where('tenant_id', $tenantId)
                ->where('id', $branchId)
                ->where('status', 1)
                ->first();
    
            if (!$branch) {
                return back()->with('error', 'Branch not found.');
            }
    
            // Get date from request - default to today
            $selectedDate = $request->get('date', now()->format('Y-m-d'));
            $dateObj = Carbon::parse($selectedDate);
            $dateStr = $dateObj->format('Y-m-d');
    
            // Get filter values
            $search = $request->get('search');
            $statusFilter = $request->get('status');
    
            // Fetch employees for this branch
            $employeesQuery = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('user_basic_details as ub', 'u.id', '=', 'ub.user_id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'ub.profile_image'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->where('uj.office_branch', $branchId);
    
            // Apply search filter
            if ($search) {
                $employeesQuery->where(function ($q) use ($search) {
                    $q->where('u.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('u.employee_id', 'LIKE', '%' . $search . '%')
                        ->orWhere('u.email', 'LIKE', '%' . $search . '%');
                });
            }
    
            $employees = $employeesQuery->get();
    
            // If no employees found
            if ($employees->isEmpty()) {
                return view('client.report.attendance.attendance-branch-wise-detail', [
                    'branch' => $branch,
                    'reportData' => [],
                    'stats' => [],
                    'selectedDate' => $dateStr,
                    'dateObj' => $dateObj,
                    'search' => $search,
                    'statusFilter' => $statusFilter,
                ]);
            }
    
            $employeeIds = $employees->pluck('id')->toArray();
    
            // Fetch attendance records for the specific date
            $attendanceRows = DB::table('attendances as a')
                ->where('a.tenant_id', $tenantId)
                ->whereIn('a.user_id', $employeeIds)
                ->where('a.date', $dateStr)
                ->get()
                ->groupBy('user_id');
    
            // Fetch approved leaves for the specific date
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereIn('user_id', $employeeIds)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->get()
                ->groupBy('user_id');
    
            // Fetch holidays for the specific date
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->get();
    
            // Fetch user weekoffs
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $employeeIds)
                ->where('status', 1)
                ->get()
                ->groupBy('user_id');
    
            // Build report data for each employee
            $reportData = [];
            $stats = [
                'total_employees' => $employees->count(),
                'present' => 0,
                'halfday' => 0,
                'checked_in_only' => 0,
                'absent' => 0,
                'on_leave' => 0,
                'holiday' => 0,
                'week_off' => 0,
            ];
    
            foreach ($employees as $employee) {
                // Get attendance records for this employee
                $attendanceRecords = $attendanceRows->get($employee->id);
                $attendance = null;
                $totalHours = null;
                
                if ($attendanceRecords) {
                    // Use the first record (there should only be one for this date)
                    $attendance = $attendanceRecords->first();
                    
                    // Calculate total hours from worked_hours
                    if ($attendance && $attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }
                }
    
                $leave = $leaveRows->get($employee->id)?->first();
                $holiday = $holidayRows->first();
                $weekoff = $weekoffRows->get($employee->id)?->first(function ($wo) use ($dateObj) {
                    if ($wo->off_type == 'day_based') {
                        return strtolower($wo->day_name) == strtolower($dateObj->format('l'));
                    }
                    if ($wo->off_type == 'date_based') {
                        return $dateObj->format('Y-m-d') >= $wo->start_date &&
                               $dateObj->format('Y-m-d') <= $wo->end_date;
                    }
                    return false;
                });
    
                // ✅ Determine status with proper priority
                $status = 'absent';
                
                // HIGHEST PRIORITY: Attendance with shift-based calculation
                if ($attendance) {
                    if ($attendance->clock_in && $attendance->clock_out) {
                        // Use shift-based logic to determine if it's half day
                        $status = $this->getAttendanceStatusByShift($totalHours, $employee->id, $dateStr, $attendance);
                        $status = strtolower($status);
                    } elseif ($attendance->clock_in && !$attendance->clock_out) {
                        // Checked In Only - Employee clocked in but not out
                        $status = 'checked_in_only';
                    }
                } 
                // THEN: Holiday
                elseif ($holiday) {
                    $status = 'holiday';
                } 
                // THEN: Leave
                elseif ($leave) {
                    $status = 'on_leave';
                } 
                // THEN: Week Off
                elseif ($weekoff) {
                    $status = 'week_off';
                }
                // FINALLY: Absent (default)
    
                // ✅ Map status to display label (no separate function)
                $displayStatus = match($status) {
                    'present' => 'Present',
                    'halfday' => 'halfday',
                    'checked_in_only' => 'checked_in_only',
                    'absent' => 'Absent',
                    'on_leave' => 'on_leave',
                    'holiday' => 'Holiday',
                    'week_off' => 'week_off',
                    default => ucfirst(str_replace('_', ' ', $status)),
                };
                
                $employeeStats = [
                    'present' => 0,
                    'halfday' => 0,
                    'checked_in_only' => 0,
                    'absent' => 0,
                    'leave' => 0,
                    'holiday' => 0,
                    'weekoff' => 0,
                ];
    
                // ✅ Count stats based on status
                if ($status === 'present') {
                    $stats['present']++;
                    $employeeStats['present'] = 1;
                } elseif ($status === 'halfday') {
                    $stats['halfday']++;
                    $stats['present']++;
                    $employeeStats['halfday'] = 1;
                } elseif ($status === 'checked_in_only') {
                    $stats['checked_in_only']++;
                    $stats['present']++;
                    $employeeStats['checked_in_only'] = 1;
                } elseif ($status === 'holiday') {
                    $stats['holiday']++;
                    $employeeStats['holiday'] = 1;
                } elseif ($status === 'on_leave') {
                    $stats['on_leave']++;
                    $employeeStats['leave'] = 1;
                } elseif ($status === 'week_off') {
                    $stats['week_off']++;
                    $employeeStats['weekoff'] = 1;
                } else {
                    $stats['absent']++;
                    $employeeStats['absent'] = 1;
                }
    
                // Build daily record for the single date
                $dailyRecords = [
                    $dateStr => [
                        'date' => $dateStr,
                        'day' => $dateObj->format('D'),
                        'status' => $displayStatus,
                        'clock_in' => $attendance->clock_in ?? null,
                        'clock_out' => $attendance->clock_out ?? null,
                        'total_hours' => $attendance->total_hours ?? null,
                        'worked_hours' => $attendance->worked_hours ?? null,
                        'late_minutes' => $attendance->late_minutes ?? 0,
                        'early_exit_minutes' => $attendance->early_departure_minutes ?? 0,
                    ]
                ];
    
                // ✅ Apply status filter with all status support
                if ($statusFilter) {
                    $filtered = false;
                    switch ($statusFilter) {
                        case 'present':
                            $filtered = in_array($status, ['present', 'halfday', 'checked_in_only']);
                            break;
                        case 'halfday':
                            $filtered = $status === 'halfday';
                            break;
                        case 'checked_in_only':
                            $filtered = $status === 'checked_in_only';
                            break;
                        case 'absent':
                            $filtered = $status === 'absent';
                            break;
                        case 'on_leave':
                            $filtered = $status === 'on_leave';
                            break;
                        case 'holiday':
                            $filtered = $status === 'holiday';
                            break;
                        case 'weekoff':
                            $filtered = $status === 'week_off';
                            break;
                        default:
                            $filtered = $status === $statusFilter;
                    }
                    if (!$filtered) {
                        continue;
                    }
                }
    
                $reportData[] = [
                    'employee' => $employee,
                    'stats' => $employeeStats,
                    'daily_records' => $dailyRecords,
                    'total_days' => 1,
                    'status' => $displayStatus,
                ];
            }
    
            return view('client.report.attendance.attendance-branch-wise-detail', [
                'branch' => $branch,
                'reportData' => $reportData,
                'stats' => $stats,
                'selectedDate' => $dateStr,
                'dateObj' => $dateObj,
                'search' => $search,
                'statusFilter' => $statusFilter,
            ]);
        } catch (Exception $e) {
            Log::error('Branch Wise Detail Report Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to generate branch detail report: ' . $e->getMessage());
        }
    }

    /**
     * Export Branch Wise Detail Report to CSV
     */
    public function branchWiseDetailExport(Request $request, $branchId)
    {
        try {
            $tenantId = session('tenant_id');
    
            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }
    
            // Get branch details
            $branch = DB::table('branches')
                ->where('tenant_id', $tenantId)
                ->where('id', $branchId)
                ->where('status', 1)
                ->first();
    
            if (!$branch) {
                return back()->with('error', 'Branch not found.');
            }
    
            // Get date from request - default to today
            $selectedDate = $request->get('date', now()->format('Y-m-d'));
            $dateObj = Carbon::parse($selectedDate);
            $dateStr = $dateObj->format('Y-m-d');
    
            // Get filter values
            $search = $request->get('search');
            $statusFilter = $request->get('status');
    
            // Fetch employees for this branch
            $employeesQuery = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->where('uj.office_branch', $branchId);
    
            if ($search) {
                $employeesQuery->where(function ($q) use ($search) {
                    $q->where('u.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('u.employee_id', 'LIKE', '%' . $search . '%');
                });
            }
    
            $employees = $employeesQuery->get();
            $employeeIds = $employees->pluck('id')->toArray();
    
            // Fetch attendance for the specific date
            $attendanceRows = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $employeeIds)
                ->where('date', $dateStr)
                ->get()
                ->groupBy('user_id');
    
            // Fetch approved leaves for the specific date
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereIn('user_id', $employeeIds)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->get()
                ->groupBy('user_id');
    
            // Fetch holidays for the specific date
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->get();
    
            // Fetch user weekoffs
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $employeeIds)
                ->where('status', 1)
                ->get()
                ->groupBy('user_id');
    
            // Generate CSV
            $filename = 'branch_attendance_' . ($branch->code ?? $branch->id) . '_' . $dateStr . '.csv';
    
            $handle = fopen('php://temp', 'w+');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
            // Headers
            $this->writeCsvRow($handle,[
                'Branch: ' . $branch->name . ' (' . ($branch->code ?? 'N/A') . ')',
                'Date: ' . $dateObj->format('d M Y'),
            ]);
            $this->writeCsvRow($handle,[]);
    
            // Main header
            $headers = [
                'SR NO.',
                'Employee ID',
                'Employee Name',
                'Email',
                'Department',
                'Designation',
                'Status',
                'Clock In',
                'Clock Out',
                'Total Hours',
                'Worked Hours',
                'Late (mins)',
                'Early Exit (mins)'
            ];
            $this->writeCsvRow($handle,$headers);
    
            // Data rows
            $srNo = 1;
            $exportStats = [
                'present' => 0,
                'halfday' => 0,
                'absent' => 0,
                'on_leave' => 0,
                'holiday' => 0,
                'week_off' => 0,
            ];
    
            foreach ($employees as $employee) {
                // Get attendance records for this employee
                $attendanceRecords = $attendanceRows->get($employee->id);
                $attendance = null;
                $totalHours = null;
                
                if ($attendanceRecords) {
                    $attendance = $attendanceRecords->first();
                    
                    if ($attendance && $attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }
                }
    
                $leave = $leaveRows->get($employee->id)?->first();
                $holiday = $holidayRows->first();
                $weekoff = $weekoffRows->get($employee->id)?->first(function ($wo) use ($dateObj) {
                    if ($wo->off_type == 'day_based') {
                        return strtolower($wo->day_name) == strtolower($dateObj->format('l'));
                    }
                    if ($wo->off_type == 'date_based') {
                        return $dateObj->format('Y-m-d') >= $wo->start_date &&
                               $dateObj->format('Y-m-d') <= $wo->end_date;
                    }
                    return false;
                });
    
                // ✅ Determine status with proper priority
                $status = 'absent';
                $displayStatus = 'Absent';
                
                if ($attendance) {
                    if ($attendance->clock_in && $attendance->clock_out) {
                        $status = $this->getAttendanceStatusByShift($totalHours, $employee->id, $dateStr, $attendance);
                        $status = strtolower($status);
                        
                        if ($status === 'present') {
                            $displayStatus = 'Present';
                            $exportStats['present']++;
                        } elseif ($status === 'halfday') {
                            $displayStatus = 'Half Day';
                            $exportStats['halfday']++;
                            $exportStats['present']++;
                        } else {
                            $displayStatus = 'Absent';
                            $exportStats['absent']++;
                        }
                    } elseif ($attendance->clock_in && !$attendance->clock_out) {
                        $status = 'checked_in_only';
                        $displayStatus = 'Checked In Only';
                        $exportStats['present']++;
                    }
                } elseif ($holiday) {
                    $status = 'holiday';
                    $displayStatus = 'Holiday';
                    $exportStats['holiday']++;
                } elseif ($leave) {
                    $status = 'on_leave';
                    $displayStatus = 'on_leave';
                    $exportStats['on_leave']++;
                } elseif ($weekoff) {
                    $status = 'week_off';
                    $displayStatus = 'Week Off';
                    $exportStats['week_off']++;
                } else {
                    $status = 'absent';
                    $displayStatus = 'Absent';
                    $exportStats['absent']++;
                }
    
                // Apply status filter
                if ($statusFilter) {
                    $filtered = false;
                    switch ($statusFilter) {
                        case 'present':
                            $filtered = in_array($status, ['present', 'halfday', 'checked_in_only']);
                            break;
                        case 'halfday':
                            $filtered = $status === 'halfday';
                            break;
                        case 'checked_in_only':
                            $filtered = $status === 'checked_in_only';
                            break;
                        case 'absent':
                            $filtered = $status === 'absent';
                            break;
                        case 'on_leave':
                            $filtered = $status === 'on_leave';
                            break;
                        case 'holiday':
                            $filtered = $status === 'holiday';
                            break;
                        case 'weekoff':
                            $filtered = $status === 'week_off';
                            break;
                        default:
                            $filtered = $status === $statusFilter;
                    }
                    if (!$filtered) {
                        continue;
                    }
                }
    
                // Format clock times
                $clockIn = $attendance && $attendance->clock_in 
                    ? Carbon::parse($attendance->clock_in)->format('h:i A') 
                    : '—';
                
                $clockOut = $attendance && $attendance->clock_out 
                    ? Carbon::parse($attendance->clock_out)->format('h:i A') 
                    : '—';
                
                $totalHoursDisplay = $attendance && $attendance->total_hours 
                    ? $attendance->total_hours 
                    : '—';
                
                $workedHoursDisplay = $attendance && $attendance->worked_hours 
                    ? number_format((float)$attendance->worked_hours, 2) . ' hrs' 
                    : '—';
    
                $this->writeCsvRow($handle,[
                    $srNo++,
                    $employee->employee_id ?? 'N/A',
                    $employee->name,
                    $employee->email ?? 'N/A',
                    $employee->department_name ?? 'N/A',
                    $employee->designation_name ?? 'N/A',
                    $displayStatus,
                    $clockIn,
                    $clockOut,
                    $totalHoursDisplay,
                    $workedHoursDisplay,
                    $attendance->late_minutes ?? 0,
                    $attendance->early_departure_minutes ?? 0,
                ]);
            }
    
            // Add summary section
            $this->writeCsvRow($handle,[]);
            $this->writeCsvRow($handle,['SUMMARY']);
            $this->writeCsvRow($handle,[
                'Total Employees',
                'Present',
                'Half Day',
                'Absent',
                'On Leave',
                'Holiday',
                'Week Off'
            ]);
            $this->writeCsvRow($handle,[
                count($employees),
                $exportStats['present'],
                $exportStats['halfday'],
                $exportStats['absent'],
                $exportStats['on_leave'],
                $exportStats['holiday'],
                $exportStats['week_off']
            ]);
    
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);
    
            return response($content)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (Exception $e) {
            Log::error('Branch Wise Detail Export Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

    /**
     * Determine user status (same as TeamController)
     */
    private function determineUserStatus($attendance, $leave, $holiday, $weekoff, $date, $userId)
    {
        // HIGHEST PRIORITY: Attendance with shift-based calculation
        if ($attendance) {
            $totalHours = null;
            if ($attendance->worked_hours) {
                $totalHours = (float)$attendance->worked_hours;
            } elseif ($attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);
                $totalHours = $clockIn->diffInHours($clockOut);
            }
    
            if ($attendance->clock_in && $attendance->clock_out) {
                return $this->getAttendanceStatusByShift($totalHours, $userId, $date, $attendance);
            } elseif ($attendance->clock_in) {
                return 'checked_in_only';
            }
        }
    
        // THEN: Holiday
        if ($holiday) {
            return 'holiday';
        }
    
        // THEN: Leave
        if ($leave) {
            if ($leave->start_session == 1 && $leave->end_session == 1) {
                return 'first_half_leave';
            } elseif ($leave->start_session == 2 && $leave->end_session == 2) {
                return 'second_half_leave';
            } else {
                return 'full_day_leave';
            }
        }
    
        // THEN: Week Off
        if ($weekoff) {
            if ($weekoff->off_type == 'day_based') {
                $dayName = Carbon::parse($date)->format('l');
                if ($weekoff->day_name == $dayName) {
                    return 'week_off';
                }
            } else {
                return 'week_off';
            }
        }
    
        // FINALLY: Absent
        return 'absent';
    }
    
}
