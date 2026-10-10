<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use App\Models\UserWeekoffs;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monthly Attendance Summary (report.attendance.summary.*) — present / absent / leave /
 * … counts per employee for a month, its CSV export and the quick summary
 * (team.attendance-summary.quick). Moved out of TeamController unchanged
 * (code-quality plan, Phase 3); route names are the same.
 */
class AttendanceSummaryController extends Controller
{
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use \App\Http\Controllers\Concerns\SanitizesCsv;
    use \App\Http\Controllers\Concerns\TeamAttendanceStatus;
    use AuthorizesByScope;

    public function attendanceSummary(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (! app(RbacService::class)->can($authUser, 'team', 'view', 'team')) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $selectedMonth = $request->get('month', date('Y-m'));

            if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
                $selectedMonth = date('Y-m');
            }

            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);
            $monthStartDate = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEndDate = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $today = date('Y-m-d');

            $attendanceEndDate = min($monthEndDate, $today);

            [$branchesEnabled, $branches, $branchId] = $this->summaryBranchContext($request, $authUser);
            $users = $this->getUsersForSummary($authUser, $branchId, $request->query('search'), $request);

            // 50 employees per page — the per-employee summary is only built for the visible page
            $page = max(1, (int) $request->get('page', 1));
            $summaryPage = new \Illuminate\Pagination\LengthAwarePaginator(
                $users->forPage($page, 50)->values(), $users->count(), 50, $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $users = $summaryPage->getCollection();

            $summaryData = [];
            $totalSummary = [
                'total_users' => 0,
                'total_present' => 0,
                'total_absent' => 0,
                'total_leave' => 0,
                'total_holiday' => 0,
                'total_weekoff' => 0,
                'total_halfday' => 0,
                'total_working_days' => 0,
            ];

            foreach ($users as $user) {
                $userSummary = $this->getUserMonthlySummary(
                    $user->id,
                    $monthStartDate,
                    $monthEndDate,
                    $attendanceEndDate
                );

                if ($userSummary) {
                    $userSummary['branch'] = $branches->firstWhere('id', $user->jobDetails?->branch_id)?->name;
                    $userSummary['attendance_location'] = \App\Models\AttendanceLocation::labelFor($user->jobDetails?->office_branch, $user->jobDetails?->attendanceLocation?->name);
                    $summaryData[] = $userSummary;

                    $totalSummary['total_users']++;
                    $totalSummary['total_present'] += $userSummary['present'];
                    $totalSummary['total_absent'] += $userSummary['absent'];
                    $totalSummary['total_leave'] += $userSummary['leaves'];
                    $totalSummary['total_holiday'] += $userSummary['holidays'];
                    $totalSummary['total_weekoff'] += $userSummary['weekoffs'];
                    $totalSummary['total_halfday'] += $userSummary['halfday'] ?? 0;
                    $totalSummary['total_working_days'] += $userSummary['working_days'];
                }
            }

            $months = $this->getMonthOptions();

            return view('client.report.attendance.attendance-summary', compact(
                'summaryData',
                'summaryPage',
                'totalSummary',
                'months',
                'selectedMonth',
                'authUser',
                'branchesEnabled',
                'branches',
                'branchId'
            ));
        } catch (Exception $e) {
            Log::error('Error in attendanceSummary: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to load attendance summary.');
        }
    }

    public function exportAttendanceSummary(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (! app(RbacService::class)->can($authUser, 'team', 'view', 'team')) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $selectedMonth = $request->get('month', date('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);
            $monthStartDate = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEndDate = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $today = date('Y-m-d');

            $attendanceEndDate = min($monthEndDate, $today);

            [$branchesEnabled, $branches, $branchId] = $this->summaryBranchContext($request, $authUser);
            $users = $this->getUsersForSummary($authUser, $branchId, $request->query('search'), $request);

            $summaryData = [];
            foreach ($users as $user) {
                $userSummary = $this->getUserMonthlySummary(
                    $user->id,
                    $monthStartDate,
                    $monthEndDate,
                    $attendanceEndDate
                );
                if ($userSummary) {
                    $userSummary['branch'] = $branches->firstWhere('id', $user->jobDetails?->branch_id)?->name;
                    $userSummary['attendance_location'] = \App\Models\AttendanceLocation::labelFor($user->jobDetails?->office_branch, $user->jobDetails?->attendanceLocation?->name);
                    $summaryData[] = $userSummary;
                }
            }

            $filename = 'attendance_summary_'.$selectedMonth.'.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ];

            $callback = function () use ($summaryData, $selectedDate, $branchesEnabled) {
                $file = fopen('php://output', 'w');

                $this->writeCsvRow($file, [
                    'Employee ID',
                    'Employee Name',
                    'Email',
                    'Department',
                    'Designation',
                    ...($branchesEnabled ? ['Branch'] : []),
                    'Attendance Location',
                    'Present Days',
                    'halfdays',
                    'Absent Days',
                    'Leaves',
                    'Holidays',
                    'Week Offs',
                    'Working Days',
                    'Attendance %',
                ]);

                foreach ($summaryData as $row) {
                    $this->writeCsvRow($file, [
                        $row['employee_id'],
                        $row['name'],
                        $row['email'],
                        $row['department'],
                        $row['designation'],
                        ...($branchesEnabled ? [$row['branch'] ?? ''] : []),
                        $row['attendance_location'] ?? '',
                        $row['present'],
                        $row['halfday'] ?? 0,
                        $row['absent'],
                        $row['leaves'],
                        $row['holidays'],
                        $row['weekoffs'],
                        $row['working_days'],
                        $row['attendance_percentage'].'%',
                    ]);
                }

                $this->writeCsvRow($file, []);
                $this->writeCsvRow($file, ['SUMMARY', '', '', '', '', '', '', '', '', '', '', '']);
                $this->writeCsvRow($file, [
                    'Total Employees: '.count($summaryData),
                    'Total Present: '.array_sum(array_column($summaryData, 'present')),
                    'Total halfdays: '.array_sum(array_column($summaryData, 'halfday') ?? [0]),
                    'Total Absent: '.array_sum(array_column($summaryData, 'absent')),
                    'Total Leaves: '.array_sum(array_column($summaryData, 'leaves')),
                    'Total Holidays: '.array_sum(array_column($summaryData, 'holidays')),
                    'Total Week Offs: '.array_sum(array_column($summaryData, 'weekoffs')),
                    '',
                    '',
                    '',
                    '',
                    'Month: '.$selectedDate->format('F Y'),
                ]);

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            Log::error('Error in exportAttendanceSummary: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to export attendance summary.');
        }
    }

    public function getQuickSummary(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (! app(RbacService::class)->can($authUser, 'team', 'view', 'team')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $selectedMonth = $request->get('month', date('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);
            $monthStartDate = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEndDate = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $today = date('Y-m-d');

            $attendanceEndDate = min($monthEndDate, $today);

            $users = $this->getUsersForSummary($authUser);

            $totalPresent = 0;
            $totalAbsent = 0;
            $totalLeaves = 0;
            $totalHolidays = 0;
            $totalWeekoffs = 0;
            $totalHalfDays = 0;
            $totalWorkingDays = 0;
            $userCount = 0;

            foreach ($users as $user) {
                $summary = $this->getUserMonthlySummary(
                    $user->id,
                    $monthStartDate,
                    $monthEndDate,
                    $attendanceEndDate
                );
                if ($summary) {
                    $userCount++;
                    $totalPresent += $summary['present'];
                    $totalAbsent += $summary['absent'];
                    $totalLeaves += $summary['leaves'];
                    $totalHolidays += $summary['holidays'];
                    $totalWeekoffs += $summary['weekoffs'];
                    $totalHalfDays += $summary['halfday'] ?? 0;
                    $totalWorkingDays += $summary['working_days'];
                }
            }

            $averageAttendance = $userCount > 0 && $totalWorkingDays > 0
                ? round(($totalPresent / $totalWorkingDays) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'month' => $selectedDate->format('F Y'),
                    'total_employees' => $userCount,
                    'total_present' => $totalPresent,
                    'total_absent' => $totalAbsent,
                    'total_leaves' => $totalLeaves,
                    'total_holidays' => $totalHolidays,
                    'total_weekoffs' => $totalWeekoffs,
                    'total_halfdays' => $totalHalfDays,
                    'average_attendance' => $averageAttendance,
                    'working_days_per_employee' => $userCount > 0 ? round($totalWorkingDays / $userCount, 1) : 0,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error in getQuickSummary: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary',
            ], 500);
        }
    }

    /**
     * Monthly Summary branch filter: only when the plan includes the Branches module.
     *
     * @return array{0: bool, 1: \Illuminate\Support\Collection, 2: ?int} [enabled, branches, selected branch id]
     */
    private function summaryBranchContext(Request $request, $authUser): array
    {
        $enabled = app(\App\Services\FeatureService::class)->enabledForCurrentTenant('branches');
        $branches = $enabled
            ? DB::table('company_branches')->where('tenant_id', $authUser->tenant_id)->where('status', 1)->orderBy('name')->get(['id', 'name'])
            : collect();

        return [$enabled, $branches, $enabled && $request->filled('branch_id') ? (int) $request->branch_id : null];
    }

    private function getUsersForSummary($authUser, ?int $branchId = null, ?string $search = null, ?Request $request = null)
    {
        $search = trim((string) $search);

        $query = User::with(['jobDetails.Department', 'jobDetails.Designation', 'jobDetails.attendanceLocation'])
            ->where('status', 1)
            ->where('role', '!=', 'admin')
            ->when($branchId, fn ($q) => $q->whereHas('jobDetails', fn ($j) => $j->where('branch_id', $branchId)))
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('name', 'LIKE', "%{$search}%")
                ->orWhere('employee_id', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%")));

        if (app(RbacService::class)->scopeFor($authUser, 'team', 'view') === 'team') {
            $query->managedBy($authUser->id);
        }

        // Branch / Department / Designation / Attendance Location (Monthly Summary report)
        if ($request) {
            $this->applyReportEmployeeFiltersToUsers($query, $request);
        }

        return $query->orderBy('name')->get();
    }

    private function getUserMonthlySummary($userId, $monthStart, $monthEnd, $dataEnd)
    {
        try {
            $user = User::with(['jobDetails.Department', 'jobDetails.Designation'])
                ->find($userId);

            if (! $user) {
                return null;
            }

            $start = Carbon::parse($monthStart);
            $calculationEnd = Carbon::parse($monthEnd);
            $dataEndDate = Carbon::parse($dataEnd);

            $totalDaysInMonth = $start->copy()->daysInMonth;

            // Tier 1 / W3 — read the persisted rollup instead of recomputing from
            // raw rows. Same output keys; gated until `attendance:summary-diff`
            // confirms parity for the tenant.
            if (config('attendance.summary_readthrough')) {
                $adapted = $this->monthlySummaryFromRollup($user, $start, $totalDaysInMonth);
                if ($adapted !== null) {
                    return $adapted;
                }
            }

            $attendances = Attendance::where('user_id', $userId)
                ->whereBetween('date', [$monthStart, $dataEnd])
                ->get()
                ->keyBy(function ($item) {
                    return Carbon::parse($item->date)->format('Y-m-d');
                });

            $leaves = Leave::where('user_id', $userId)
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

            $holidays = Holiday::where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                        $q2->where('start_date', '<=', $monthStart)
                            ->where('start_date', '>=', $monthEnd);
                    });
            })->get();

            $weekoffs = UserWeekoffs::where('user_id', $userId)
                ->where('status', 1)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            $present = 0;
            $absent = 0;
            $leaveCount = 0;
            $holidayCount = 0;
            $weekoffCount = 0;
            $halfDayCount = 0;

            $countedDates = [];

            // Count holidays
            foreach ($holidays as $holiday) {
                $holidayStart = Carbon::parse($holiday->start_date);
                $holidayEnd = Carbon::parse($holiday->start_date);

                for ($d = $holidayStart->copy(); $d->lte($holidayEnd); $d->addDay()) {
                    $dateStr = $d->format('Y-m-d');
                    if ($d->between($start, $calculationEnd) && ! in_array($dateStr, $countedDates)) {
                        $holidayCount++;
                        $countedDates[] = $dateStr;
                    }
                }
            }

            // Count weekoffs
            foreach ($weekoffs as $weekoff) {
                $weekoffStart = Carbon::parse($weekoff->start_date);
                $weekoffEnd = Carbon::parse($weekoff->end_date);

                for ($d = $weekoffStart->copy(); $d->lte($weekoffEnd); $d->addDay()) {
                    $dateStr = $d->format('Y-m-d');

                    if (! $d->between($start, $calculationEnd)) {
                        continue;
                    }

                    if (in_array($dateStr, $countedDates)) {
                        continue;
                    }

                    if ($weekoff->off_type == 'day_based') {
                        $currentDay = $d->format('l');
                        if ($currentDay == $weekoff->day_name) {
                            $weekoffCount++;
                            $countedDates[] = $dateStr;
                        }
                    } else {
                        $weekoffCount++;
                        $countedDates[] = $dateStr;
                    }
                }
            }

            // Count leaves
            foreach ($leaves as $leave) {
                $leaveStart = Carbon::parse($leave->start_date);
                $leaveEnd = Carbon::parse($leave->start_date);

                for ($d = $leaveStart->copy(); $d->lte($leaveEnd); $d->addDay()) {
                    $dateStr = $d->format('Y-m-d');

                    if (! $d->between($start, $calculationEnd)) {
                        continue;
                    }

                    if (in_array($dateStr, $countedDates)) {
                        continue;
                    }

                    $leaveCount++;
                    $countedDates[] = $dateStr;
                }
            }

            // Count present/absent/halfday from attendance with shift-based logic
            for ($date = $start->copy(); $date->lte($dataEndDate); $date->addDay()) {
                $dateStr = $date->format('Y-m-d');

                if (in_array($dateStr, $countedDates)) {
                    continue;
                }

                $attendance = $attendances->get($dateStr);

                if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    // ✅ FIX: Use worked_hours for calculation
                    $totalHours = null;
                    if ($attendance->worked_hours) {
                        $totalHours = (float) $attendance->worked_hours;
                    } else {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }

                    $status = $this->getAttendanceStatusByShift($totalHours, $userId, $dateStr, $attendance);

                    if ($status === 'Present') {
                        $present++;
                    } elseif ($status === 'halfday') {
                        // A worked half day counts as 0.5 present — it must NOT
                        // also add 0.5 to absent (that let present + absent
                        // exceed the number of working days).
                        $halfDayCount++;
                        $present += 0.5;
                    } else {
                        $absent++;
                    }
                } elseif ($attendance && $attendance->clock_in) {
                    $present++;
                } else {
                    $absent++;
                }
            }

            $workingDays = $totalDaysInMonth - $holidayCount - $weekoffCount;

            return [
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->jobDetails->Department->name ?? 'N/A',
                'designation' => $user->jobDetails->Designation->name ?? 'N/A',
                'joining_date' => $user->jobDetails->joining_date ?? 'N/A',
                'present' => (float) $present,
                'absent' => (float) $absent,
                'leaves' => (float) $leaveCount,
                'holidays' => $holidayCount,
                'weekoffs' => $weekoffCount,
                'halfday' => $halfDayCount,
                'working_days' => $workingDays,
                'total_month_days' => $totalDaysInMonth,
                'attendance_percentage' => $workingDays > 0 ? round(($present / $workingDays) * 100, 2) : 0,
            ];
        } catch (Exception $e) {
            Log::error('Error in getUserMonthlySummary: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Tier 1 / W3 — build the getUserMonthlySummary() payload from the persisted
     * attendance_summaries row. Returns null (caller falls back to the live
     * recompute) if no rollup exists yet.
     *
     * Key mapping (must stay identical to the inline path's return array):
     *   present  = present_days + 0.5 * half_days   (a worked half day is 0.5 present)
     *   halfday  = half_days
     *   leaves   = total_leaves
     */
    private function monthlySummaryFromRollup($user, Carbon $start, int $totalDaysInMonth): ?array
    {
        $m = app(\App\Services\AttendanceSummaryService::class)
            ->getMonthly((int) $user->id, $start->format('Y-m'), (int) $user->tenant_id);

        if (($m['source'] ?? null) === 'unavailable') {
            return null;
        }

        $halfDays = (float) $m['half_days'];
        $present = (float) $m['present_days'] + 0.5 * $halfDays;
        $absent = (float) $m['absent_days'];
        $leaveCount = (float) $m['total_leaves'];
        $holidayCount = (int) $m['holidays'];
        $weekoffCount = (int) $m['week_offs'];
        $workingDays = $totalDaysInMonth - $holidayCount - $weekoffCount;

        return [
            'user_id' => $user->id,
            'employee_id' => $user->employee_id,
            'name' => $user->name,
            'email' => $user->email,
            'department' => $user->jobDetails->Department->name ?? 'N/A',
            'designation' => $user->jobDetails->Designation->name ?? 'N/A',
            'joining_date' => $user->jobDetails->joining_date ?? 'N/A',
            'present' => (float) $present,
            'absent' => (float) $absent,
            'leaves' => (float) $leaveCount,
            'holidays' => $holidayCount,
            'weekoffs' => $weekoffCount,
            'halfday' => (int) $halfDays,
            'working_days' => $workingDays,
            'total_month_days' => $totalDaysInMonth,
            'attendance_percentage' => $workingDays > 0 ? round(($present / $workingDays) * 100, 2) : 0,
        ];
    }
}
