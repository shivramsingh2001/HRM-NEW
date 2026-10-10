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
 * Employee-wise attendance / Monthly Summary (one employee day by day, or every employee's month) + its CSV export.
 * Moved out of AttendanceReportController unchanged (code-quality plan, Phase 3);
 * route names are the same.
 */
class EmployeeWiseAttendanceReportController extends Controller
{
    use \App\Http\Controllers\Concerns\AttendanceReportHelpers;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use SanitizesCsv;

    /**
     * Display employee-wise detailed attendance report
     */
    public function employeeWiseAttendance(Request $request)
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
            $employeeIdFilter = $request->get('employee_id');
            $branchFilter = $request->get('branch_id');

            // Fetch all employees with details
            $employeesQuery = DB::table('users as u')
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
                    'cb.name as branch_name'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->orderBy('u.name');

            // Apply filters
            if ($employeeIdFilter) {
                $employeesQuery->where('u.id', (int) $employeeIdFilter);
            }

            if ($departmentFilter) {
                $employeesQuery->where('uj.department', (int) $departmentFilter);
            }

            if ($branchFilter) {
                $employeesQuery->where('uj.branch_id', (int) $branchFilter);
            }

            if ($search) {
                $employeesQuery->where(function ($q) use ($search) {
                    $q->where('u.name', 'LIKE', '%'.$search.'%')
                        ->orWhere('u.employee_id', 'LIKE', '%'.$search.'%');
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

            // Get branches for filter
            $branches = DB::table('company_branches')
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
                    'branches' => $branches,
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
                            ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                            ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                                $q2->where('start_date', '<=', $monthStart)
                                    ->where('end_date', '>=', $monthEnd);
                            });
                    })
                    ->get();

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
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->end_date;
                    });
                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->end_date;
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
                        $totalHours = (float) $attendance->worked_hours;
                    } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }

                    if ($attendance) {
                        $dayData['in_time'] = $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('h:i A') : '--';
                        $dayData['out_time'] = clock_out_time($attendance->clock_out, $attendance->date, 'h:i A', '--');

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
                        } elseif ($attendance->clock_in && ! $attendance->clock_out) {
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
                            ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                            ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                                $q2->where('start_date', '<=', $monthStart)
                                    ->where('end_date', '>=', $monthEnd);
                            });
                    })
                    ->get();

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
                        return $dateStr >= $lv->start_date && $dateStr <= $lv->end_date;
                    });
                    $holiday = $holidays->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->end_date;
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
                        $totalHours = (float) $attendance->worked_hours;
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
                        } elseif ($attendance->clock_in && ! $attendance->clock_out) {
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
                'branches' => $branches,
                'selectedMonth' => $month,
                'selectedDate' => $selectedDate,
                'daysInMonth' => $daysInMonth,
                'monthStart' => $monthStart,
                'employee' => null,
                'dailyData' => [],
                'summary' => [],
            ]);
        } catch (Exception $e) {
            Log::error('Employee Wise Attendance Error: '.$e->getMessage());

            return back()->with('error', 'Failed to generate report: '.$e->getMessage());
        }
    }

    /**
     * CSV of the Employee Wise Attendance / Monthly Summary page with the same
     * filters (month, employee_id, search, department, branch …): one line per
     * employee per day, then a totals line per employee. Built from the page's
     * own data (employeeWiseAttendance) so the file always matches the screen.
     * The routes report.attendance.detailed.export / monthly.summary.export
     * pointed at this method but it did not exist (every export was a 500).
     * Hours are H:MM and never wrap at 24 h; a next-day clock-out is marked "(+1 day)".
     */
    public function employeeWisExportReport(Request $request)
    {
        $page = $this->employeeWiseAttendance($request);
        if (! $page instanceof \Illuminate\View\View) {
            return $page; // redirect with an error (no tenant, bad month …)
        }

        $data = $page->getData();
        $groups = $data['employee']
            ? [['employee' => $data['employee'], 'dailyData' => $data['dailyData'], 'summary' => $data['summary']]]
            : $data['employeeData'];
        $monthStart = Carbon::parse($data['monthStart']);
        $filename = 'employee_wise_attendance_'.$data['selectedMonth'].($data['employee'] ? '_'.($data['employee']->employee_id ?: $data['employee']->id) : '').'.csv';

        return response()->streamDownload(function () use ($groups, $monthStart) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee', 'Employee ID', 'Date', 'Day', 'Status', 'In', 'Out', 'Shift From', 'Shift To', 'Working', 'Short Hours', 'OT']);
            foreach ($groups as $g) {
                $emp = $g['employee'];
                foreach ($g['dailyData'] as $day => $d) {
                    // One employee: full day detail; all employees (Monthly Summary): just the status code.
                    $d = is_array($d) ? $d : ['status' => $d];
                    $date = $monthStart->copy()->day((int) $day);
                    fputcsv($out, [
                        $emp->name, $emp->employee_id, $date->format('Y-m-d'), $date->format('D'), $d['status'] ?? '',
                        $d['in_time'] ?? '', $d['out_time'] ?? '', $d['shift_from'] ?? '', $d['shift_to'] ?? '',
                        $d['working'] ?? '', $d['short_hrs'] ?? '', $d['ot_times'] ?? '',
                    ]);
                }
                $s = $g['summary'];
                fputcsv($out, [
                    $emp->name, $emp->employee_id, 'TOTAL', '',
                    'P '.($s['present'] ?? 0).' / A '.($s['absent'] ?? 0).' / HD '.($s['halfday'] ?? 0).' / WO '.($s['weekoff'] ?? 0).' / H '.($s['holiday'] ?? 0).' / L '.($s['paid_days'] ?? 0),
                    '', '', '', '',
                    $this->minutesToTime((int) ($s['work_hours'] ?? 0)),
                    $this->minutesToTime((int) ($s['short_hours'] ?? 0)),
                    $this->minutesToTime((int) ($s['ot_hours'] ?? 0)),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
