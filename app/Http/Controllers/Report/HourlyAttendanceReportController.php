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
 * Working Hours report + its CSV export.
 * Moved out of AttendanceReportController unchanged (code-quality plan, Phase 3);
 * route names are the same.
 */
class HourlyAttendanceReportController extends Controller
{
    use \App\Http\Controllers\Concerns\AttendanceReportHelpers;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use SanitizesCsv;

    /**
     * Hourly Attendance Report (Matrix)
     */
    public function hourlyAttendanceReport(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (! $tenantId) {
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
            $branchFilter = $request->get('branch_id');

            // Fetch all active employees
            $employees = DB::table('users as u')
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
                    'uj.department as department_id',
                    'uj.branch_id',
                    'cb.name as branch_name'
                )
                ->tap(fn ($q) => $this->applyReportEmployeeFilters($q, $request))
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->orderBy('u.name')
                ->get();

            // Apply department filter
            if ($departmentFilter) {
                $employees = $employees->where('department_id', (int) $departmentFilter);
            }

            // Apply branch filter
            if ($branchFilter) {
                $employees = $employees->where('branch_id', (int) $branchFilter);
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
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get()
                ->groupBy('user_id');

            // Fetch holidays
            $holidays = DB::table('holidays')
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
                    'branch' => $employee->branch_name ?? '--',
                    'attendance_location' => \App\Models\AttendanceLocation::labelFor($employee->office_branch, $employee->location_name),
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
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->end_date;
                    });

                    // Check for holiday
                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->end_date;
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
                        $totalHours = (float) $attendance->worked_hours;
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
                    } elseif ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
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

            $branches = DB::table('company_branches')
                ->where('tenant_id', $tenantId)->where('status', 1)
                ->select('id', 'name')->orderBy('name')->get();

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
                'branches' => $branches,
                'paginator' => new \Illuminate\Pagination\LengthAwarePaginator(
                    $paginatedData,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => route('report.attendance.hourly.index'), 'query' => $request->query()]
                ),
            ]);
        } catch (Exception $e) {

            Log::error('Attendance Matrix Report Error: '.$e->getMessage());

            return back()->with('error', 'Failed to generate report: '.$e->getMessage());
        }
    }

    /**
     * Export Hourly Attendance Report to CSV
     */
    public function hourlyExportAttendance(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (! $tenantId) {
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
                ->leftJoin('attendance_locations as al', 'uj.office_branch', '=', 'al.id')
                ->leftJoin('company_branches as cb', 'uj.branch_id', '=', 'cb.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.office_branch',
                    'al.name as location_name',
                    'uj.department as department_id',
                    'uj.branch_id',
                    'cb.name as branch_name'
                )
                ->tap(fn ($q) => $this->applyReportEmployeeFilters($q, $request))
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->orderBy('u.name')
                ->get();

            // Apply filters
            if ($request->get('department')) {
                $employees = $employees->where('department_id', (int) $request->get('department'));
            }

            if ($request->get('branch_id')) {
                $employees = $employees->where('branch_id', (int) $request->get('branch_id'));
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
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get()
                ->groupBy('user_id');

            $holidays = DB::table('holidays')
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
            $csvData[] = ['Firm Name: '.($tenant->company_name ?? 'Company Name')];
            $csvData[] = ['Attendance Report'];
            $csvData[] = ['Month: '.$selectedDate->format('F, Y')];
            $csvData[] = [];

            // Add header row
            $header = ['S.No', 'Emp Code', 'Name', 'Designation', 'Department', 'Branch', 'Attendance Location'];
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                $header[] = $date->format('d-m-y').' ('.$date->format('D').')';
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
                    $employee->department_name ?? '--',
                    $employee->branch_name ?? '--',
                    \App\Models\AttendanceLocation::labelFor($employee->office_branch, $employee->location_name),
                ];

                $totalMinutes = 0;

                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $date = Carbon::createFromFormat('Y-m-d', $monthStart)->addDays($i - 1);
                    $dateStr = $date->format('Y-m-d');

                    $attendance = $employeeAttendances->first(function ($att) use ($dateStr) {
                        return $att->date == $dateStr;
                    });

                    $leave = $employeeLeaves->first(function ($lv) use ($dateStr) {
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->end_date;
                    });

                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->end_date;
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
                        $totalHours = (float) $attendance->worked_hours;
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
                    } elseif ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
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
            $filename = 'Attendance_Report_'.$selectedDate->format('F_Y').'.csv';

            $handle = fopen('php://temp', 'w+');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            foreach ($csvData as $row) {
                $this->writeCsvRow($handle, $row);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        } catch (Exception $e) {
            Log::error('Attendance Matrix Export Error: '.$e->getMessage());

            return back()->with('error', 'Failed to export: '.$e->getMessage());
        }
    }
}
