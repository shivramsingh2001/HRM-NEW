<?php
// app/Http/Controllers/Performance/PerformanceController.php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmployeeKpiScore;
use App\Models\EmployeeDailyPerformance;
use App\Models\Department;
use App\Models\AttendanceRegularization;
use App\Models\ManagerPerformanceReview;
use App\Services\Performance\PerformanceRollupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;

class PerformanceController extends Controller
{
    use AuthorizesByScope;

    /**
     * My Performance Dashboard (Employee self-view)
     */
    public function myPerformance(Request $request)
    {
        $user = auth()->user();
        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $reportingMonth = $month . '-01';

        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        $currentMonth = now()->format('Y-m');
        if ($nextMonth > $currentMonth) {
            $nextMonth = null;
        }

        $prevReportingMonth = $prevMonth . '-01';

        $kpiScore = EmployeeKpiScore::where('user_id', $user->id)
            ->where('reporting_month', $reportingMonth)
            ->first();

        if (!$kpiScore) {
            $kpiScore = EmployeeKpiScore::where('user_id', $user->id)
                ->orderBy('reporting_month', 'desc')
                ->first();
        }

        $prevKpiScore = EmployeeKpiScore::where('user_id', $user->id)
            ->where('reporting_month', $prevReportingMonth)
            ->first();
        $prevOverallScore = $prevKpiScore->overall_score ?? null;

        $history = EmployeeKpiScore::where('user_id', $user->id)
            ->orderBy('reporting_month', 'desc')
            ->take(6)
            ->get();

        [$dailyPerformance, $weeklyPerformance] = $this->periodBreakdown($user->id, (int) $user->tenant_id, $month);

        return view('client.performance.my-dashboard', compact(
            'kpiScore',
            'history',
            'month',
            'prevMonth',
            'nextMonth',
            'prevOverallScore',
            'dailyPerformance',
            'weeklyPerformance'
        ));
    }

    /**
     * Daily rows + on-demand weekly buckets for one user's month — feeds the
     * Daily/Weekly/Monthly trend toggle on the dashboard/individual-report
     * views. Weekly buckets are 7-day chunks starting at the month's first
     * calculable day (mirrors PerformanceRollupService::rollupMonth()'s own
     * range clamping), never persisted.
     */
    private function periodBreakdown(int $userId, int $tenantId, string $month): array
    {
        $monthStart = Carbon::parse($month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $rangeEnd = $monthEnd->lt(Carbon::today()) ? $monthEnd : Carbon::today();

        // performance_date is cast to a Carbon date. Model::serializeDate()
        // always calls Carbon::toJSON() (UTC ISO), which for an IST tenant
        // renders as the PRIOR calendar day client-side — and would silently
        // mismatch the DATE column if echoed back as the `date` param to
        // dailyDetail(). Re-assigning the cast attribute does NOT avoid this
        // (the cast re-applies on every read), so we map to plain arrays
        // with an explicit Y-m-d string instead of returning Eloquent rows.
        $dailyPerformance = EmployeeDailyPerformance::where('user_id', $userId)
            ->where('calculation_status', 'calculated')
            ->whereBetween('performance_date', [$monthStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->orderBy('performance_date')
            ->get([
                'performance_date', 'overall_daily_score', 'attendance_score', 'task_completion_score',
                'task_ontime_score', 'project_participation_score', 'regularization_score',
                'day_type', 'attendance_status',
            ])
            ->map(fn ($row) => [
                'performance_date' => $row->performance_date->format('Y-m-d'),
                'overall_daily_score' => $row->overall_daily_score,
                'attendance_score' => $row->attendance_score,
                'task_completion_score' => $row->task_completion_score,
                'task_ontime_score' => $row->task_ontime_score,
                'project_participation_score' => $row->project_participation_score,
                'regularization_score' => $row->regularization_score,
                'day_type' => $row->day_type,
                'attendance_status' => $row->attendance_status,
            ]);

        $rollup = app(PerformanceRollupService::class);
        $weeklyPerformance = [];
        $cursor = $monthStart->copy();
        $weekNo = 1;
        while ($cursor->lte($rangeEnd)) {
            $weeklyPerformance[] = array_merge(
                $rollup->weekly($userId, $tenantId, $cursor->format('Y-m-d')),
                ['label' => 'Week ' . $weekNo]
            );
            $cursor->addDays(7);
            $weekNo++;
        }

        return [$dailyPerformance, $weeklyPerformance];
    }

    /**
     * Individual Employee Report (For HR/Managers)
     */
    public function individualReport($userId, Request $request, PerformanceRollupService $rollup)
    {
        $employee = User::findOrFail($userId);
        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $reportingMonth = $month . '-01';

        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        $currentMonth = now()->format('Y-m');
        if ($nextMonth > $currentMonth) {
            $nextMonth = null;
        }

        $prevReportingMonth = $prevMonth . '-01';

        if (!$this->scopeCoversOwner(auth()->user(), 'performance', 'view', $employee->id)) {
            abort(403, 'Unauthorized access');
        }

        $kpiScore = EmployeeKpiScore::where('user_id', $userId)
            ->where('reporting_month', $reportingMonth)
            ->first();

        if (!$kpiScore) {
            // Fast on-demand rollup, guarded by a short lock — aggregates
            // already-precomputed daily rows rather than re-deriving from raw
            // attendance/task data, so this stays cheap even under concurrent
            // requests for the same employee+month (the old inline
            // calculate-then-create() here could race on the unique
            // (user_id,reporting_month) key and 500).
            $kpiScore = Cache::lock("perf-rollup-{$employee->id}-{$month}", 10)
                ->block(5, fn () => $rollup->rollupMonth($employee, $month));
        }

        $overallScore = $kpiScore->overall_score;
        $grade = $kpiScore->grade;

        $prevKpiScore = EmployeeKpiScore::where('user_id', $userId)
            ->where('reporting_month', $prevReportingMonth)
            ->first();

        if ($prevKpiScore) {
            $prevOverallScore = $prevKpiScore->overall_score;
            $scoreDifference = $prevOverallScore !== null && $overallScore !== null ? $overallScore - $prevOverallScore : null;
            $scoreTrend = $scoreDifference === null ? 'stable' : ($scoreDifference > 0 ? 'up' : ($scoreDifference < 0 ? 'down' : 'stable'));
        } else {
            $prevOverallScore = null;
            $scoreDifference = null;
            $scoreTrend = 'stable';
        }

        $history = EmployeeKpiScore::where('user_id', $userId)
            ->orderBy('reporting_month', 'desc')
            ->take(6)
            ->get();

        $departmentId = $employee->jobDetails?->department;
        $departmentAvg = null;
        if ($departmentId) {
            $departmentScores = EmployeeKpiScore::where('reporting_month', $reportingMonth)
                ->whereHas('user.jobDetails', function ($q) use ($departmentId) {
                    $q->where('department', $departmentId);
                })
                ->whereNotNull('overall_score')
                ->get();

            if ($departmentScores->count() > 0) {
                $departmentAvg = round($departmentScores->avg('overall_score'), 2);
            }
        }

        $companyScores = EmployeeKpiScore::where('reporting_month', $reportingMonth)
            ->whereNotNull('overall_score')
            ->get();
        $companyAvg = $companyScores->count() > 0 ? round($companyScores->avg('overall_score'), 2) : null;

        $monthlyBreakdown = [
            'attendance' => $kpiScore->attendance_score ?? 0,
            'task_completion' => $kpiScore->task_completion_score ?? 0,
            'deadline_met' => $kpiScore->deadline_met_score ?? 0,
            'regularization' => $kpiScore->regularization_score ?? 0,
            'project_participation' => $kpiScore->project_participation_score ?? 0,
            'manager_rating' => $kpiScore->manager_rating_score ?? 0,
        ];

        $nextMonthExists = false;
        if ($nextMonth) {
            $nextMonthExists = EmployeeKpiScore::where('user_id', $userId)
                ->where('reporting_month', $nextMonth . '-01')
                ->exists();
        }

        $regularizationDetails = null;
        if ($kpiScore->regularization_count > 0) {
            $regularizationDetails = AttendanceRegularization::where('user_id', $userId)
                ->whereBetween('date', [Carbon::parse($reportingMonth)->startOfMonth(), Carbon::parse($reportingMonth)->endOfMonth()])
                ->get(['id', 'date', 'request_type', 'status', 'reason']);
        }

        $managerReview = ManagerPerformanceReview::where('user_id', $userId)
            ->where('review_month', $reportingMonth)
            ->where('status', 'submitted')
            ->first();

        [$dailyPerformance, $weeklyPerformance] = $this->periodBreakdown($employee->id, (int) $employee->tenant_id, $month);

        return view('client.performance.individual-report', compact(
            'employee',
            'kpiScore',
            'history',
            'month',
            'prevMonth',
            'nextMonth',
            'departmentAvg',
            'companyAvg',
            'overallScore',
            'grade',
            'prevOverallScore',
            'scoreDifference',
            'scoreTrend',
            'monthlyBreakdown',
            'nextMonthExists',
            'regularizationDetails',
            'managerReview',
            'dailyPerformance',
            'weeklyPerformance'
        ));
    }

    /**
     * AJAX: one day's full performance breakdown, for the day-detail drawer.
     */
    public function dailyDetail(Request $request, int $userId)
    {
        $employee = User::findOrFail($userId);
        if (!$this->scopeCoversOwner(auth()->user(), 'performance', 'view', $employee->id)) {
            abort(403, 'Unauthorized access');
        }

        $date = $request->get('date', now()->subDay()->format('Y-m-d'));

        $row = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $employee->tenant_id)
            ->where('user_id', $employee->id)
            ->where('performance_date', $date)
            ->first();

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'No data calculated for this date yet.'], 404);
        }

        // See periodBreakdown()'s comment: don't return the Eloquent model
        // directly — its date cast re-serializes performance_date as a UTC
        // ISO string, which for an IST tenant renders as the wrong day.
        $data = $row->toArray();
        $data['performance_date'] = $row->performance_date->format('Y-m-d');

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Average a column across a collection, excluding null values from both
     * the sum and the denominator — never coerces "no data" into 0.
     */
    private function avgOrNull($scores, string $column): ?float
    {
        $withValue = $scores->whereNotNull($column);

        return $withValue->isNotEmpty() ? round((float) $withValue->avg($column), 2) : null;
    }

    /**
     * Team Consolidated Report
     */
    public function teamReport(Request $request)
    {
        $user = auth()->user();

        // Employees cannot access team report (requires at least team-level
        // performance:view — the "own" scope employees hold doesn't qualify)
        if (!app(RbacService::class)->can($user, 'performance', 'view', 'team')) {
            abort(403, 'You are not authorized to view team reports.');
        }

        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $departmentId = $request->get('department_id');
        $reportingMonth = $month . '-01';

        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        $currentMonth = now()->format('Y-m');
        if ($nextMonth > $currentMonth) {
            $nextMonth = null;
        }

        $query = EmployeeKpiScore::with('user.jobDetails')
            ->where('reporting_month', $reportingMonth)
            ->where('tenant_id', $user->tenant_id);

        if ($departmentId) {
            $query->whereHas('user.jobDetails', function ($q) use ($departmentId) {
                $q->where('department', $departmentId);
            });
        }

        if ($request->get('employee_id')) {
            $query->where('user_id', $request->get('employee_id'));
        }

        if ($user->role == 'manager') {
            $query->whereHas('user', function ($q) use ($user) {
                $q->managedBy($user->id);
            });
        }

        $scores = $query->get();

        $teamData = [];
        foreach ($scores as $score) {
            $teamData[] = (object) [
                'user' => $score->user,
                'attendance_score' => $score->attendance_score ?? 0,
                'task_score' => $score->task_completion_score ?? 0,
                'deadline_score' => $score->deadline_met_score ?? 0,
                'regularization_score' => $score->regularization_score ?? 0,
                'project_participation_score' => $score->project_participation_score ?? 0,
                'manager_rating_score' => $score->manager_rating_score ?? 0,
                'manager_rating_raw' => $score->manager_rating_raw ?? 0,
                'overall_score' => $score->overall_score ?? 0,
                'grade' => $score->grade ?? 'N/A',
                'overtime' => $score->overtime_hours,
                'late_count' => $score->late_count ?? 0,
                'regularization_count' => $score->regularization_count ?? 0,
                'days_calculated' => $score->days_calculated,
                'days_expected' => $score->days_expected,
            ];
        }

        usort($teamData, fn ($a, $b) => $b->overall_score <=> $a->overall_score);
        foreach ($teamData as $index => $data) {
            $data->rank = $index + 1;
        }

        // Paginate the already-ranked array (not the query) so rank/averages
        // reflect the WHOLE filtered team, not just the current page.
        $perPage = (int) $request->get('per_page', 20);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $teamData = new LengthAwarePaginator(
            array_slice($teamData, ($currentPage - 1) * $perPage, $perPage),
            count($teamData),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $totals = [
            'avg_attendance' => $this->avgOrNull($scores, 'attendance_score'),
            'avg_task' => $this->avgOrNull($scores, 'task_completion_score'),
            'avg_deadline' => $this->avgOrNull($scores, 'deadline_met_score'),
            'avg_regularization' => $this->avgOrNull($scores, 'regularization_score'),
            'avg_project_participation' => $this->avgOrNull($scores, 'project_participation_score'),
            'avg_manager_rating' => $this->avgOrNull($scores, 'manager_rating_score'),
            'total_overtime' => $scores->sum('overtime_hours'),
            'team_size' => $scores->count(),
            'avg_overall' => $this->avgOrNull($scores, 'overall_score'),
        ];

        if (in_array($user->role, ['admin', 'hr'])) {
            $departments = Department::where('status', 1)->get();
        } else {
            $managerDept = $user->jobDetails?->department;
            $departments = $managerDept ? Department::where('id', $managerDept)->get() : collect();
        }

        $employeeQuery = User::where('status', '1')
            ->where('role', 'employee')
            ->where('tenant_id', $user->tenant_id);

        if ($user->role == 'manager') {
            $employeeQuery->managedBy($user->id);
        }

        $allEmployees = $employeeQuery->orderBy('name')->get();

        $availableMonths = EmployeeKpiScore::where('tenant_id', $user->tenant_id)
            ->select('reporting_month')
            ->distinct()
            ->orderBy('reporting_month', 'desc')
            ->limit(12)
            ->get()
            ->map(fn ($item) => Carbon::parse($item->reporting_month)->format('Y-m'));

        return view('client.performance.team-report', compact(
            'teamData',
            'totals',
            'departments',
            'month',
            'prevMonth',
            'nextMonth',
            'availableMonths',
            'allEmployees'
        ));
    }
}
