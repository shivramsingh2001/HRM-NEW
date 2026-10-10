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
 * Attendance Location Wise report (per location, then one location's employees) + its CSV export.
 * Moved out of AttendanceReportController unchanged (code-quality plan, Phase 3);
 * route names are the same.
 */
class LocationAttendanceReportController extends Controller
{
    use \App\Http\Controllers\Concerns\AttendanceReportHelpers;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use SanitizesCsv;

    public function branchWiseAttendanceReport(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (! $tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            // Date range (start/end) — defaults to today; old `?date=` still works.
            [$startObj, $endObj, $rangeNote] = $this->locationReportRange($request);
            $startDate = $startObj->format('Y-m-d');
            $endDate = $endObj->format('Y-m-d');
            $rangeDates = collect(\Carbon\CarbonPeriod::create($startObj, $endObj))->map(fn ($d) => $d->copy());
            $search = trim((string) $request->get('search', ''));

            // Fetch all active attendance locations (optionally filtered by name/description)
            $branches = DB::table('attendance_locations')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")))
                ->when($this->reportEmployeeFilters($request)['location_id'] !== null,
                    fn ($q) => $q->where('id', $this->reportEmployeeFilters($request)['location_id']))
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get();

            // 50 location cards per page (stats are only computed for the visible page)
            $page = max(1, (int) $request->get('page', 1));
            $locationPage = new \Illuminate\Pagination\LengthAwarePaginator(
                $branches->forPage($page, 50)->values(), $branches->count(), 50, $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $branches = $locationPage->getCollection();

            $branchStats = [];

            foreach ($branches as $branch) {
                // Get employees for this branch using office_branch field
                $employeeIds = DB::table('users as u')
                    ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                    ->tap(fn ($q) => $this->applyReportEmployeeFilters($q, $request))
                    ->where('u.tenant_id', $tenantId)
                    ->where('u.status', 1)
                    ->where('u.role', '!=', 'admin')
                    ->where('uj.office_branch', $branch->id)
                    ->pluck('u.id')
                    ->toArray();

                $branchStats[$branch->id] = [
                    'branch' => $branch,
                    'total_employees' => count($employeeIds),
                    // employee-days in the range (employees × days) — the base for the %
                    'employee_days' => count($employeeIds) * $rangeDates->count(),
                    'present' => 0,
                    'halfday' => 0,
                    'absent' => 0,
                    'on_leave' => 0,
                    'holiday' => 0,
                    'week_off' => 0,
                ];

                if (! empty($employeeIds)) {
                    // Fetch attendance for the whole range
                    $attendances = DB::table('attendances')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('user_id', $employeeIds)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->get()
                        ->groupBy(fn ($a) => $a->user_id.'|'.Carbon::parse($a->date)->format('Y-m-d'));

                    // Fetch approved leaves overlapping the range
                    $leaves = DB::table('leaves')
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'approved')
                        ->whereIn('user_id', $employeeIds)
                        ->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate)
                        ->get()
                        ->groupBy('user_id');

                    // Fetch holidays overlapping the range
                    $holidays = DB::table('holidays')
                        ->where('tenant_id', $tenantId)
                        ->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate)
                        ->get();

                    // Fetch user weekoffs
                    $weekoffs = DB::table('user_weekoffs')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('user_id', $employeeIds)
                        ->where('status', 1)
                        ->get()
                        ->groupBy('user_id');

                    // Calculate stats for each employee for each day in the range
                    foreach ($rangeDates as $dateObj) {
                        $dateStr = $dateObj->format('Y-m-d');
                        foreach ($employeeIds as $employeeId) {
                            // Check if ANY attendance record has clock_in for this employee
                            $attendanceRecords = $attendances->get($employeeId.'|'.$dateStr);
                            $hasClockIn = false;
                            $attendance = null;
                            $totalHours = null;

                            if ($attendanceRecords) {
                                foreach ($attendanceRecords as $record) {
                                    if (! is_null($record->clock_in)) {
                                        $hasClockIn = true;
                                        $attendance = $record;
                                        // Calculate total hours from worked_hours
                                        if ($attendance->worked_hours) {
                                            $totalHours = (float) $attendance->worked_hours;
                                        } elseif ($attendance->clock_in && $attendance->clock_out) {
                                            $clockIn = Carbon::parse($attendance->clock_in);
                                            $clockOut = Carbon::parse($attendance->clock_out);
                                            $totalHours = $clockIn->diffInHours($clockOut);
                                        }
                                        break;
                                    }
                                }
                            }

                            $leave = $leaves->get($employeeId)?->first(fn ($l) => substr($l->start_date, 0, 10) <= $dateStr && substr($l->end_date, 0, 10) >= $dateStr);
                            $holiday = $holidays->first(fn ($h) => substr($h->start_date, 0, 10) <= $dateStr && substr($h->end_date, 0, 10) >= $dateStr);
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
                'locationPage' => $locationPage,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dayCount' => $rangeDates->count(),
                'search' => $search,
                'rangeNote' => $rangeNote,
            ]);
        } catch (Exception $e) {
            Log::error('Attendance Location Wise Report Error: '.$e->getMessage());
            Log::error('Trace: '.$e->getTraceAsString());

            return back()->with('error', 'Failed to generate attendance location wise report: '.$e->getMessage());
        }
    }

    /**
     * Attendance Location Wise detail - employees assigned to one attendance
     * location, one row per employee per day in the selected date range.
     */
    public function branchWiseDetailReport(Request $request, $branchId)
    {
        try {
            $tenantId = session('tenant_id');

            if (! $tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $branch = DB::table('attendance_locations')
                ->where('tenant_id', $tenantId)
                ->where('id', $branchId)
                ->where('status', 1)
                ->first();

            if (! $branch) {
                return back()->with('error', 'Attendance location not found.');
            }

            [$start, $end, $rangeNote] = $this->locationReportRange($request);
            $search = trim((string) $request->get('search', ''));
            $statusFilter = $request->get('status');

            [$rows, $stats] = $this->locationDetailRows($request, (int) $tenantId, (int) $branchId, $start, $end, $search, $statusFilter);

            $perPage = 50;
            $page = max(1, (int) $request->get('page', 1));
            $reportData = new \Illuminate\Pagination\LengthAwarePaginator(
                array_slice($rows, ($page - 1) * $perPage, $perPage),
                count($rows),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return view('client.report.attendance.attendance-branch-wise-detail', [
                'branch' => $branch,
                'reportData' => $reportData,
                'stats' => $stats,
                'startDate' => $start->format('Y-m-d'),
                'endDate' => $end->format('Y-m-d'),
                'dayCount' => $start->diffInDays($end) + 1,
                'rangeNote' => $rangeNote,
                'search' => $search,
                'statusFilter' => $statusFilter,
            ]);
        } catch (Exception $e) {
            Log::error('Attendance Location Detail Report Error: '.$e->getMessage());
            Log::error('Trace: '.$e->getTraceAsString());

            return back()->with('error', 'Failed to generate attendance location detail report: '.$e->getMessage());
        }
    }

    /**
     * Export the Attendance Location Wise detail report to CSV (same filters as the page).
     */
    public function branchWiseDetailExport(Request $request, $branchId)
    {
        try {
            $tenantId = session('tenant_id');

            if (! $tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $branch = DB::table('attendance_locations')
                ->where('tenant_id', $tenantId)
                ->where('id', $branchId)
                ->where('status', 1)
                ->first();

            if (! $branch) {
                return back()->with('error', 'Attendance location not found.');
            }

            [$start, $end] = $this->locationReportRange($request);
            [$rows, $stats] = $this->locationDetailRows($request, (int) $tenantId, (int) $branchId, $start, $end, $request->get('search'), $request->get('status'));

            $period = $start->equalTo($end) ? $start->format('d M Y') : $start->format('d M Y').' to '.$end->format('d M Y');
            $filename = 'attendance_location_'.($branch->code ?? $branch->id).'_'.$start->format('Y-m-d')
                .($start->equalTo($end) ? '' : '_to_'.$end->format('Y-m-d')).'.csv';

            $statusLabels = ['present' => 'Present', 'halfday' => 'Half Day', 'checked_in_only' => 'Checked In Only',
                'absent' => 'Absent', 'on_leave' => 'On Leave', 'holiday' => 'Holiday', 'week_off' => 'Week Off'];

            $handle = fopen('php://temp', 'w+');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            $this->writeCsvRow($handle, [
                'Attendance Location: '.$branch->name.' ('.($branch->code ?? 'N/A').')',
                'Date: '.$period,
            ]);
            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, [
                'SR NO.', 'Date', 'Day', 'Employee ID', 'Employee Name', 'Email', 'Department', 'Designation', 'Branch',
                'Attendance Location', 'Status', 'Clock In', 'Clock Out', 'Total Hours', 'Worked Hours', 'Late (mins)', 'Early Exit (mins)',
            ]);

            foreach ($rows as $i => $r) {
                $e = $r['employee'];
                $this->writeCsvRow($handle, [
                    $i + 1,
                    Carbon::parse($r['date'])->format('d M Y'),
                    Carbon::parse($r['date'])->format('D'),
                    $e->employee_id ?? 'N/A',
                    $e->name,
                    $e->email ?? 'N/A',
                    $e->department_name ?? 'N/A',
                    $e->designation_name ?? 'N/A',
                    $e->branch_name ?? 'N/A',
                    \App\Models\AttendanceLocation::labelFor($e->office_branch, $e->location_name),
                    $statusLabels[$r['status']] ?? ucfirst(str_replace('_', ' ', $r['status'])),
                    $r['clock_in'] ? Carbon::parse($r['clock_in'])->format('h:i A') : '—',
                    $r['clock_out'] ? Carbon::parse($r['clock_out'])->format('h:i A') : '—',
                    $r['total_hours'] ?: '—',
                    $r['worked_hours'] ? number_format((float) $r['worked_hours'], 2).' hrs' : '—',
                    $r['late_minutes'],
                    $r['early_exit_minutes'],
                ]);
            }

            // Summary (employee-days over the range)
            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, ['SUMMARY']);
            $this->writeCsvRow($handle, ['Total Employees', 'Days', 'Present', 'Half Day', 'Absent', 'On Leave', 'Holiday', 'Week Off']);
            $this->writeCsvRow($handle, [
                $stats['total_employees'], $start->diffInDays($end) + 1, $stats['present'], $stats['halfday'],
                $stats['absent'], $stats['on_leave'], $stats['holiday'], $stats['week_off'],
            ]);

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        } catch (Exception $e) {
            Log::error('Attendance Location Detail Export Error: '.$e->getMessage());
            Log::error('Trace: '.$e->getTraceAsString());

            return back()->with('error', 'Failed to export: '.$e->getMessage());
        }
    }

    /**
     * Start/end date range for the Attendance Location Wise pages. Defaults to
     * today; the old single `?date=` still works (start = end = date). Capped at
     * 93 days because every day is evaluated per employee.
     *
     * @return array{0: Carbon, 1: Carbon, 2: ?string} [start, end, note when capped]
     */
    private function locationReportRange(Request $request): array
    {
        $today = now()->format('Y-m-d');
        $legacyDate = $request->get('date');

        try {
            $start = Carbon::parse($request->get('start_date', $legacyDate ?: $today))->startOfDay();
            $end = Carbon::parse($request->get('end_date', $legacyDate ?: $today))->startOfDay();
        } catch (Exception $e) {
            report($e);
            $start = $end = Carbon::parse($today);
        }
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $note = null;
        if ($start->diffInDays($end) > 92) {
            $end = $start->copy()->addDays(92);
            $note = 'Date range limited to 93 days — showing '.$start->format('d M Y').' to '.$end->format('d M Y').'.';
        }

        return [$start, $end, $note];
    }

    /**
     * Rows for the Attendance Location Wise detail page + its CSV: one row per
     * employee assigned to the location per day in the range, with that day's
     * status. Shared by branchWiseDetailReport() and branchWiseDetailExport().
     */
    private function locationDetailRows(Request $request, int $tenantId, int $locationId, Carbon $start, Carbon $end, ?string $search, ?string $statusFilter): array
    {
        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');
        $search = trim((string) $search);

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
                'cb.name as branch_name',
                'uj.office_branch',
                'al.name as location_name'
            )
            ->tap(fn ($q) => $this->applyReportEmployeeFilters($q, $request))
            ->where('u.tenant_id', $tenantId)
            ->where('u.status', 1)
            ->where('u.role', '!=', 'admin')
            ->where('uj.office_branch', $locationId)
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('u.name', 'LIKE', "%{$search}%")
                ->orWhere('u.employee_id', 'LIKE', "%{$search}%")
                ->orWhere('u.email', 'LIKE', "%{$search}%")))
            ->orderBy('u.name')
            ->get();

        $stats = ['total_employees' => $employees->count(), 'present' => 0, 'halfday' => 0, 'checked_in_only' => 0,
            'absent' => 0, 'on_leave' => 0, 'holiday' => 0, 'week_off' => 0];
        $rows = [];

        if ($employees->isEmpty()) {
            return [$rows, $stats];
        }

        $employeeIds = $employees->pluck('id')->all();

        $attendances = DB::table('attendances')
            ->where('tenant_id', $tenantId)
            ->whereIn('user_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn ($a) => $a->user_id.'|'.Carbon::parse($a->date)->format('Y-m-d'));

        $leaves = DB::table('leaves')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereIn('user_id', $employeeIds)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get()
            ->groupBy('user_id');

        $holidays = DB::table('holidays')
            ->where('tenant_id', $tenantId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        $weekoffs = DB::table('user_weekoffs')
            ->where('tenant_id', $tenantId)
            ->whereIn('user_id', $employeeIds)
            ->where('status', 1)
            ->get()
            ->groupBy('user_id');

        foreach (\Carbon\CarbonPeriod::create($start, $end) as $day) {
            $dateStr = $day->format('Y-m-d');
            $holiday = $holidays->first(fn ($h) => substr($h->start_date, 0, 10) <= $dateStr && substr($h->end_date, 0, 10) >= $dateStr);

            foreach ($employees as $employee) {
                // First record of the day that has a clock-in (multi-punch days keep one row per day)
                $attendance = $attendances->get($employee->id.'|'.$dateStr)?->first(fn ($a) => ! is_null($a->clock_in));

                $leave = $leaves->get($employee->id)?->first(fn ($l) => substr($l->start_date, 0, 10) <= $dateStr && substr($l->end_date, 0, 10) >= $dateStr);
                $weekoff = $weekoffs->get($employee->id)?->first(function ($wo) use ($day, $dateStr) {
                    if ($wo->off_type == 'day_based') {
                        return strtolower($wo->day_name) == strtolower($day->format('l'));
                    }
                    if ($wo->off_type == 'date_based') {
                        return $dateStr >= $wo->start_date && $dateStr <= $wo->end_date;
                    }

                    return false;
                });

                // Same priority as before: attendance → holiday → leave → week off → absent
                $status = 'absent';
                if ($attendance) {
                    if ($attendance->clock_out) {
                        $hours = $attendance->worked_hours
                            ? (float) $attendance->worked_hours
                            : Carbon::parse($attendance->clock_in)->diffInHours(Carbon::parse($attendance->clock_out));
                        $status = strtolower($this->getAttendanceStatusByShift($hours, $employee->id, $dateStr, $attendance));
                    } else {
                        $status = 'checked_in_only';
                    }
                } elseif ($holiday) {
                    $status = 'holiday';
                } elseif ($leave) {
                    $status = 'on_leave';
                } elseif ($weekoff) {
                    $status = 'week_off';
                }
                if ($status === 'half day') {
                    $status = 'halfday';
                }

                // Stats count every employee-day (before the status filter), as before
                if (in_array($status, ['present', 'halfday', 'checked_in_only'], true)) {
                    $stats['present']++;
                    if ($status !== 'present') {
                        $stats[$status]++;
                    }
                } elseif (isset($stats[$status])) {
                    $stats[$status]++;
                } else {
                    $stats['absent']++;
                }

                if ($statusFilter) {
                    $keep = match ($statusFilter) {
                        'present' => in_array($status, ['present', 'halfday', 'checked_in_only'], true),
                        'weekoff' => $status === 'week_off',
                        default => $status === $statusFilter,
                    };
                    if (! $keep) {
                        continue;
                    }
                }

                $rows[] = [
                    'employee' => $employee,
                    'date' => $dateStr,
                    'status' => $status,
                    'clock_in' => $attendance->clock_in ?? null,
                    'clock_out' => $attendance->clock_out ?? null,
                    'total_hours' => $attendance->total_hours ?? null,
                    'worked_hours' => $attendance->worked_hours ?? null,
                    'late_minutes' => $attendance->late_minutes ?? 0,
                    'early_exit_minutes' => $attendance->early_departure_minutes ?? 0,
                ];
            }
        }

        return [$rows, $stats];
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
                $totalHours = (float) $attendance->worked_hours;
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
