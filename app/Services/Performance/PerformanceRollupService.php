<?php

namespace App\Services\Performance;

use App\Models\EmployeeDailyPerformance;
use App\Models\EmployeeKpiScore;
use App\Models\ManagerPerformanceReview;
use App\Models\User;
use Carbon\Carbon;

/**
 * Aggregates App\Models\EmployeeDailyPerformance rows into the existing
 * monthly `employee_kpi_scores` table (rollupMonth), or into an on-demand
 * weekly figure (weekly() — never persisted, matching the
 * attendances/attendance_summaries precedent of no weekly table).
 *
 * The ONLY writer of employee_kpi_scores.overall_score/grade — every
 * consumer (controllers, the review-submission flow) calls this instead of
 * re-implementing a weighted average, closing the old 4-duplicate-formula bug.
 */
class PerformanceRollupService
{
    public function __construct(
        private PerformancePolicyResolver $policies,
        private PerformanceScoreCalculator $calc,
    ) {
    }

    public function rollupMonth(User $user, string $month): EmployeeKpiScore
    {
        $tenantId = (int) ($user->tenant_id ?: User::withoutGlobalScopes()->whereKey($user->id)->value('tenant_id'));
        $monthStart = Carbon::parse($month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $today = Carbon::today();

        $rangeStart = $monthStart->copy();
        $joiningDate = $user->jobDetails?->joining_date;
        if ($joiningDate && Carbon::parse($joiningDate)->gt($rangeStart)) {
            $rangeStart = Carbon::parse($joiningDate)->startOfDay();
        }
        $rangeEnd = $monthEnd->lt($today) ? $monthEnd : $today;

        $policy = $this->policies->forTenantMonth($tenantId, $month);

        $daysExpected = $rangeStart->lte($rangeEnd) ? $rangeStart->diffInDays($rangeEnd) + 1 : 0;

        $rows = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereBetween('performance_date', [$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->orderBy('performance_date')
            ->get();

        $calculatedRows = $rows->where('calculation_status', 'calculated');
        $daysCalculated = $calculatedRows->count();

        $objectiveScore = $daysCalculated > 0
            ? round((float) $calculatedRows->avg('overall_daily_score'), 2)
            : null;

        $breakdown = $this->summarizeBreakdown($rows);

        $managerReview = ManagerPerformanceReview::where('user_id', $user->id)
            ->where('review_month', $monthStart->format('Y-m-d'))
            ->where('status', 'submitted')
            ->first();
        $managerRatingScore = $managerReview ? round($managerReview->overall_rating * 20, 2) : null;

        $blend = $this->calc->blend(
            ['objective' => $objectiveScore, 'manager_rating' => $managerRatingScore],
            $policy->monthlyWeights()
        );
        $overallScore = $blend['score'];
        $grade = $this->calc->grade($overallScore);

        return EmployeeKpiScore::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $user->id, 'reporting_month' => $monthStart->format('Y-m-d')],
            array_merge($breakdown, [
                'attendance_score' => $this->avgOf($calculatedRows, 'attendance_score'),
                'task_completion_score' => $this->avgOf($calculatedRows, 'task_completion_score'),
                'deadline_met_score' => $this->avgOf($calculatedRows, 'task_ontime_score'),
                'regularization_score' => $this->avgOf($calculatedRows, 'regularization_score'),
                'project_participation_score' => $this->avgOf($calculatedRows, 'project_participation_score'),
                'manager_rating_score' => $managerRatingScore,
                'manager_rating_raw' => $managerReview?->overall_rating,
                'manager_feedback' => $managerReview?->additional_feedback,
                'manager_rated_by' => $managerReview?->reviewer_id,
                'manager_rated_at' => $managerReview?->submitted_at,
                'manager_rating_included' => in_array('manager_rating', $blend['included'], true),
                'overall_score' => $overallScore,
                'grade' => $grade,
                'working_days_in_period' => $rows->where('day_type', 'working')->count(),
                'days_calculated' => $daysCalculated,
                'days_expected' => $daysExpected,
                'policy_effective_from' => $policy->effectiveFrom,
                'weights_snapshot' => $blend['weights_used'],
                'calculation_audit' => [
                    'range' => ['start' => $rangeStart->format('Y-m-d'), 'end' => $rangeEnd->format('Y-m-d')],
                    'objective_score' => $objectiveScore,
                    'blend' => $blend,
                ],
                'status' => 'calculated',
                'calculated_at' => now(),
            ])
        );
    }

    /**
     * On-demand weekly figure — never persisted. No manager-rating blend
     * (manager rating has no weekly meaning).
     */
    public function weekly(int $userId, int $tenantId, string $weekStart): array
    {
        $start = Carbon::parse($weekStart)->startOfDay();
        $end = $start->copy()->addDays(6);

        $rows = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('performance_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        $calculatedRows = $rows->where('calculation_status', 'calculated');
        $score = $calculatedRows->isNotEmpty() ? round((float) $calculatedRows->avg('overall_daily_score'), 2) : null;

        return array_merge($this->summarizeBreakdown($rows), [
            'week_start' => $start->format('Y-m-d'),
            'week_end' => $end->format('Y-m-d'),
            'overall_score' => $score,
            'grade' => $this->calc->grade($score),
            'days_calculated' => $calculatedRows->count(),
            'days_expected' => $start->diffInDays($end) + 1,
            'attendance_score' => $this->avgOf($calculatedRows, 'attendance_score'),
            'task_completion_score' => $this->avgOf($calculatedRows, 'task_completion_score'),
            'task_ontime_score' => $this->avgOf($calculatedRows, 'task_ontime_score'),
            'project_participation_score' => $this->avgOf($calculatedRows, 'project_participation_score'),
            'regularization_score' => $this->avgOf($calculatedRows, 'regularization_score'),
        ]);
    }

    /**
     * Called after a manager review is submitted/updated — re-blends the
     * period's already-computed objective score with the new manager rating
     * via the one canonical formula, instead of each controller
     * re-implementing its own weighted average.
     */
    public function recalculateMonthlyOverall(EmployeeKpiScore $kpiScore): void
    {
        $tenantId = (int) $kpiScore->tenant_id;
        $month = Carbon::parse($kpiScore->reporting_month)->format('Y-m');
        $policy = $this->policies->forTenantMonth($tenantId, $month);

        $objectiveScore = $this->objectiveScoreFor($kpiScore);

        $managerReview = ManagerPerformanceReview::where('user_id', $kpiScore->user_id)
            ->where('review_month', Carbon::parse($kpiScore->reporting_month)->format('Y-m-d'))
            ->where('status', 'submitted')
            ->first();
        $managerRatingScore = $managerReview ? round($managerReview->overall_rating * 20, 2) : null;

        $blend = $this->calc->blend(
            ['objective' => $objectiveScore, 'manager_rating' => $managerRatingScore],
            $policy->monthlyWeights()
        );

        $kpiScore->update([
            'manager_rating_score' => $managerRatingScore,
            'manager_rating_raw' => $managerReview?->overall_rating,
            'manager_feedback' => $managerReview?->additional_feedback,
            'manager_rated_by' => $managerReview?->reviewer_id,
            'manager_rated_at' => $managerReview?->submitted_at,
            'manager_rating_included' => in_array('manager_rating', $blend['included'], true),
            'overall_score' => $blend['score'],
            'grade' => $this->calc->grade($blend['score']),
            'weights_snapshot' => $blend['weights_used'],
        ]);
    }

    /**
     * The objective (non-manager) monthly average this KPI row was last
     * rolled up with. If the row predates the daily engine (no
     * days_calculated recorded), falls back to a plain mean of whichever
     * objective component scores are already stored — never fabricates data.
     */
    private function objectiveScoreFor(EmployeeKpiScore $kpiScore): ?float
    {
        if ($kpiScore->days_calculated) {
            $audit = $kpiScore->calculation_audit;
            if (is_array($audit) && array_key_exists('objective_score', $audit)) {
                return $audit['objective_score'];
            }
        }

        $components = array_filter([
            $kpiScore->attendance_score,
            $kpiScore->task_completion_score,
            $kpiScore->deadline_met_score,
            $kpiScore->regularization_score,
            $kpiScore->project_participation_score,
        ], fn ($v) => $v !== null);

        return count($components) ? round(array_sum($components) / count($components), 2) : null;
    }

    /** @param \Illuminate\Support\Collection<int,EmployeeDailyPerformance> $rows */
    private function summarizeBreakdown($rows): array
    {
        $calculated = $rows->where('calculation_status', 'calculated');

        $present = $rows->whereIn('attendance_status', ['present', 'late', 'overtime', 'early_departure']);
        $regularizations = $rows->whereNotNull('regularization_status');
        $lastCalculated = $calculated->last();

        return [
            'present_days' => $present->count(),
            'absent_days' => $rows->where('attendance_status', 'absent')->count(),
            'half_days' => $rows->where('attendance_status', 'half_day')->count(),
            'late_days' => $rows->where('is_late', true)->count(),
            'early_departure_days' => $rows->where('is_early_departure', true)->count(),
            'paid_leaves' => $rows->where('day_type', 'full_leave_paid')->count(),
            'unpaid_leaves' => $rows->where('day_type', 'full_leave_unpaid')->count(),
            'holidays' => $rows->where('day_type', 'holiday')->count(),
            'weekoffs' => $rows->where('day_type', 'weekoff')->count(),
            'assigned_tasks' => (int) $rows->sum('assigned_tasks_count'),
            'completed_tasks' => (int) $rows->sum('completed_tasks_count'),
            'on_time_completed_tasks' => (int) $rows->sum('on_time_completed_tasks_count'),
            'late_completed_tasks' => (int) $rows->sum('late_completed_tasks_count'),
            'overdue_tasks' => (int) ($lastCalculated?->overdue_tasks_count ?? 0),
            'project_assigned_tasks' => (int) $rows->sum('project_assigned_tasks_count'),
            'project_completed_tasks' => (int) $rows->sum('project_completed_tasks_count'),
            'project_on_time_tasks' => (int) $rows->sum('project_on_time_tasks_count'),
            'regularization_count' => $regularizations->count(),
            'approved_regularization_count' => $rows->where('regularization_status', 'approved')->count(),
            'rejected_regularization_count' => $rows->where('regularization_status', 'rejected')->count(),
            'pending_regularization_count' => $rows->where('regularization_status', 'pending')->count(),
            'late_count' => $rows->where('is_late', true)->count(),
            'total_late_minutes' => (int) $rows->sum('late_minutes'),
        ];
    }

    /** @param \Illuminate\Support\Collection<int,EmployeeDailyPerformance> $rows */
    private function avgOf($rows, string $column): ?float
    {
        $withValue = $rows->whereNotNull($column);

        return $withValue->isNotEmpty() ? round((float) $withValue->avg($column), 2) : null;
    }
}
