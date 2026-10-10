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
 * Overall Attendance (per-employee totals for a range) + its CSV export.
 * Moved out of AttendanceReportController unchanged (code-quality plan, Phase 3);
 * route names are the same.
 */
class OverallAttendanceReportController extends Controller
{
    use \App\Http\Controllers\Concerns\AttendanceReportHelpers;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use SanitizesCsv;

    /**
     * Overall Attendance Report
     */
    public function overallAttendanceReport(Request $request)
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

            // Get filter values
            $search = $request->get('search');
            $departmentFilter = $request->get('department');
            $branchFilter = $request->get('branch_id');

            // Fetch employees with details
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
                    'employee_email' => $employee->email,
                    'employee_id' => $employee->employee_id ?? '--',
                    'designation' => $employee->designation_name ?? '--',
                    'department' => $employee->department_name ?? '--',
                    'branch' => $employee->branch_name ?? '--',
                    'attendance_location' => \App\Models\AttendanceLocation::labelFor($employee->office_branch, $employee->location_name),
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
                    } elseif ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
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

            $branches = DB::table('company_branches')
                ->where('tenant_id', $tenantId)->where('status', 1)
                ->select('id', 'name')->orderBy('name')->get();

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
                'branches' => $branches,
                'paginator' => new \Illuminate\Pagination\LengthAwarePaginator(
                    $paginatedData,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => route('report.attendance.overall.index'), 'query' => $request->query()]
                ),
            ]);
        } catch (Exception $e) {
            Log::error('Overall Attendance Report Error: '.$e->getMessage());

            return back()->with('error', 'Failed to generate report: '.$e->getMessage());
        }
    }

    /**
     * Export Overall Attendance Report to CSV
     */
    public function overallExportAttendance(Request $request)
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

            // Get tenant details
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();

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

            // Add header information
            $csvData[] = ['Firm Name: '.($tenant->company_name ?? 'Company Name')];
            $csvData[] = ['Attendance Report'];
            $csvData[] = ['Month: '.$selectedDate->format('F, Y')];
            $csvData[] = [];

            // Build header row
            $header = ['Employee ID', 'Employee Name', 'Designation', 'Department', 'Branch', 'Attendance Location'];
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
                    $employee->department_name ?? '--',
                    $employee->branch_name ?? '--',
                    \App\Models\AttendanceLocation::labelFor($employee->office_branch, $employee->location_name),
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
                    } elseif ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
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
            $filename = 'Attendance_Overall_Report_'.$selectedDate->format('F_Y').'.csv';

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
            Log::error('Attendance Export Error: '.$e->getMessage());

            return back()->with('error', 'Failed to export: '.$e->getMessage());
        }
    }
}
