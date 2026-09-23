<?php

namespace App\Services\Performance;

use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\ProjectAssign;
use App\Models\TaskAssign;
use App\Models\UserWeekoffs;
use App\Services\Attendance\AttendanceDayResolver;
use App\Services\Attendance\PolicyResolver as AttendancePolicyResolver;
use App\Services\LeaveService;
use Carbon\Carbon;

/**
 * Pure-ish (DB-read-only, no writes) daily scorer for one (tenant, user, date).
 *
 * Reuses App\Services\Attendance\AttendanceDayResolver — the app's own
 * canonical "what is this employee's status on this day" classifier —
 * instead of re-deriving attendance classification from scratch (the old
 * PerformanceCalculationService's bug-prone approach) or depending on
 * attendance_summaries' cached daily_breakdown JSON, which may not be fresh
 * for a just-finished "yesterday". This also means attendance-policy changes
 * (present/half-day ratios, grace) are honoured automatically.
 *
 * Every query is tenant-safe: withoutGlobalScopes() + an explicit tenant_id
 * filter, matching AttendanceEntryService/AttendanceSummaryService's
 * established pattern — this fixes the old service's cross-tenant Holiday
 * leak. Never resolves tenant via app('current_tenant').
 */
class PerformanceDailyScorer
{
    /** Attendance-day tokens that are excluded from the attendance score (not a performance failure). */
    private const EXCLUDED_DAY_TYPES = [
        'holiday' => 'holiday',
        'week_off' => 'weekoff',
        'paid_leave' => 'full_leave_paid',
        'unpaid_leave' => 'full_leave_unpaid',
        'first_half_leave' => 'half_leave',
        'second_half_leave' => 'half_leave',
    ];

    public function __construct(
        private AttendanceDayResolver $dayResolver,
        private AttendancePolicyResolver $attendancePolicies,
        private PerformancePolicyResolver $performancePolicies,
        private PerformanceScoreCalculator $calc,
        private LeaveService $leaveService,
    ) {
    }

    /**
     * @return array<string,mixed> columns ready for EmployeeDailyPerformance::updateOrCreate(),
     *                              or ['calculation_status' => 'pending'] for a future date.
     */
    public function scoreDay(int $userId, int $tenantId, string $date): array
    {
        $date = Carbon::parse($date)->format('Y-m-d');
        $policy = $this->performancePolicies->forTenantDate($tenantId, $date);

        $attendance = $this->scoreAttendance($userId, $tenantId, $date, $policy);

        if ($attendance['skip']) {
            return ['calculation_status' => 'pending'];
        }

        $regularization = $this->scoreRegularization($userId, $tenantId, $date, $policy);
        $tasks = $this->scoreTasks($userId, $tenantId, $date, $policy);
        $project = $this->scoreProjectParticipation($userId, $tenantId, $date);

        $components = [
            'attendance' => $attendance['score'],
            'task_completion' => $tasks['completion_score'],
            'task_ontime' => $tasks['ontime_score'],
            'project_participation' => $project['score'],
            'regularization' => $regularization['score'],
        ];

        $blend = $this->calc->blend($components, $policy->dailyWeights());
        $calculationStatus = $blend['score'] === null ? 'excluded' : 'calculated';

        return [
            'day_type' => $attendance['day_type'],
            'attendance_status' => $attendance['attendance_status'],
            'worked_hours' => $attendance['worked_hours'],
            'late_minutes' => $attendance['late_minutes'],
            'early_departure_minutes' => $attendance['early_minutes'],
            'is_late' => $attendance['is_late'],
            'is_early_departure' => $attendance['is_early'],
            'is_unauthorized_absent' => $attendance['is_unauthorized_absent'],
            'regularization_id' => $regularization['id'],
            'regularization_status' => $regularization['status'],
            'assigned_tasks_count' => $tasks['assigned'],
            'completed_tasks_count' => $tasks['completed'],
            'on_time_completed_tasks_count' => $tasks['on_time'],
            'late_completed_tasks_count' => $tasks['late'],
            'overdue_tasks_count' => $tasks['overdue'],
            'project_assigned_tasks_count' => $project['assigned'],
            'project_completed_tasks_count' => $project['completed'],
            'project_on_time_tasks_count' => $project['on_time'],
            'attendance_score' => $attendance['score'],
            'task_completion_score' => $tasks['completion_score'],
            'task_ontime_score' => $tasks['ontime_score'],
            'project_participation_score' => $project['score'],
            'regularization_score' => $regularization['score'],
            'overall_daily_score' => $blend['score'],
            'calculation_status' => $calculationStatus,
            'policy_effective_from' => $policy->effectiveFrom,
            'components_included' => ['included' => $blend['included'], 'weights_used' => $blend['weights_used']],
            'calculation_audit' => [
                'attendance' => $attendance,
                'regularization' => $regularization,
                'tasks' => $tasks,
                'project' => $project,
            ],
        ];
    }

    // ------------------------------------------------------------------

    private function scoreAttendance(int $userId, int $tenantId, string $date, PerformancePolicySnapshot $policy): array
    {
        $row = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->first();
        $dayRows = $row ? [$row] : [];

        $leaves = Leave::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        $leaveDetail = [];
        foreach ($leaves as $leave) {
            $leaveDetail[$leave->id] = $this->leaveService->isLwpId($leave->leave_type) ? 'unpaid' : 'paid';
        }

        $isHoliday = Holiday::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('status', 1)
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->exists();

        $isWeekoff = $this->isWeekoff($userId, $tenantId, $date);
        $attendancePolicy = $this->attendancePolicies->forTenantDate($tenantId, $date);

        $resolved = $this->dayResolver->resolve($dayRows, $leaves, $leaveDetail, $isHoliday, $isWeekoff, $date, $attendancePolicy);
        $token = $resolved['token'];

        if ($token === 'upcoming') {
            return ['skip' => true];
        }

        if (isset(self::EXCLUDED_DAY_TYPES[$token])) {
            return [
                'skip' => false,
                'day_type' => self::EXCLUDED_DAY_TYPES[$token],
                'attendance_status' => $token,
                'score' => null,
                'worked_hours' => $resolved['worked_hours'],
                'late_minutes' => 0,
                'early_minutes' => 0,
                'is_late' => false,
                'is_early' => false,
                'is_unauthorized_absent' => false,
            ];
        }

        if ($token === 'checked_in_only') {
            // Data incomplete (no clock-out yet, or a missed punch) — excluded
            // rather than penalized; the attendance module's own auto-clockout/
            // regularization flow handles the underlying gap.
            return [
                'skip' => false,
                'day_type' => 'working',
                'attendance_status' => 'checked_in_only',
                'score' => null,
                'worked_hours' => $resolved['worked_hours'],
                'late_minutes' => (int) $resolved['late_minutes'],
                'early_minutes' => 0,
                'is_late' => false,
                'is_early' => false,
                'is_unauthorized_absent' => false,
            ];
        }

        // present | late | overtime | early_departure | half_day | absent
        $lateMinutes = (int) $resolved['late_minutes'];
        $earlyMinutes = (int) $resolved['early_departure_minutes'];
        $isLate = $lateMinutes > $policy->lateGraceMinutes;
        $isEarly = $earlyMinutes > $policy->earlyDepartureGraceMinutes;
        $isUnauthorizedAbsent = ($token === 'absent');

        $base = ((float) $resolved['day_fraction']) * 100.0;
        $latePenalty = $isLate ? min($policy->latePenaltyPerIncident, $policy->latePenaltyCap) : 0.0;
        $earlyPenalty = $isEarly ? min($policy->earlyDeparturePenaltyPerIncident, $policy->earlyDeparturePenaltyCap) : 0.0;
        $score = max(0.0, $base - $latePenalty - $earlyPenalty);

        return [
            'skip' => false,
            'day_type' => 'working',
            'attendance_status' => $token,
            'score' => round($score, 2),
            'worked_hours' => $resolved['worked_hours'],
            'late_minutes' => $lateMinutes,
            'early_minutes' => $earlyMinutes,
            'is_late' => $isLate,
            'is_early' => $isEarly,
            'is_unauthorized_absent' => $isUnauthorizedAbsent,
        ];
    }

    private function isWeekoff(int $userId, int $tenantId, string $date): bool
    {
        $dayName = Carbon::parse($date)->format('l');

        return UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 1)
            ->where(function ($q) use ($date, $dayName) {
                $q->where(function ($qq) use ($date) {
                    $qq->where('off_type', 'date_based')
                        ->where('start_date', '<=', $date)->where('end_date', '>=', $date);
                })->orWhere(function ($qq) use ($dayName) {
                    $qq->where('off_type', 'day_based')->where('day_name', $dayName);
                });
            })
            ->exists();
    }

    private function scoreRegularization(int $userId, int $tenantId, string $date, PerformancePolicySnapshot $policy): array
    {
        $reg = AttendanceRegularization::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->orderByDesc('id')
            ->first();

        if (! $reg) {
            return ['score' => null, 'id' => null, 'status' => null];
        }

        $penalty = match ($reg->status) {
            'approved' => $policy->regularizationPenaltyApproved,
            'rejected' => $policy->regularizationPenaltyRejected,
            'pending' => $policy->regularizationPenaltyPending,
            default => 0.0,
        };
        $penalty = min($penalty, $policy->regularizationPenaltyCap);

        return [
            'score' => round(max(0.0, 100.0 - $penalty), 2),
            'id' => $reg->id,
            'status' => $reg->status,
        ];
    }

    private function scoreTasks(int $userId, int $tenantId, string $date, PerformancePolicySnapshot $policy): array
    {
        $assigned = TaskAssign::withoutGlobalScopes()
            ->join('tasks', 'tasks.id', '=', 'task_assigns.task_id')
            ->where('task_assigns.tenant_id', $tenantId)
            ->where('task_assigns.assigned_to', $userId)
            ->whereDate('tasks.task_date', $date)
            ->select('task_assigns.individual_status', 'task_assigns.completed_at', 'task_assigns.updated_at', 'tasks.deadline_date as task_deadline_date')
            ->get();

        $assignedCount = $assigned->count();
        $completed = $assigned->where('individual_status', 'completed');
        $completedCount = $completed->count();

        $onTimeCount = 0;
        foreach ($completed as $a) {
            if ($this->completedOnTime($a->completed_at ?? $a->updated_at, $a->task_deadline_date)) {
                $onTimeCount++;
            }
        }
        $lateCount = $completedCount - $onTimeCount;

        // Task::scopeOverdue()'s exact definition, snapshotted as of $date.
        $overdueCount = TaskAssign::withoutGlobalScopes()
            ->join('tasks', 'tasks.id', '=', 'task_assigns.task_id')
            ->where('task_assigns.tenant_id', $tenantId)
            ->where('task_assigns.assigned_to', $userId)
            ->where('tasks.deadline_date', '<', $date)
            ->whereNotIn('tasks.status', ['completed', 'cancelled', 'approved'])
            ->count();

        $completionScore = $assignedCount >= $policy->minTasksForTaskScore
            ? round(($completedCount / $assignedCount) * 100, 2)
            : null;

        $ontimeScore = null;
        if ($completedCount > 0) {
            $base = ($onTimeCount / $completedCount) * 100;
            $overduePenalty = min($overdueCount * $policy->taskOverduePenaltyPerTask, $policy->taskOverduePenaltyCap);
            $ontimeScore = round(max(0, $base - $overduePenalty), 2);
        }

        return [
            'assigned' => $assignedCount,
            'completed' => $completedCount,
            'on_time' => $onTimeCount,
            'late' => $lateCount,
            'overdue' => $overdueCount,
            'completion_score' => $completionScore,
            'ontime_score' => $ontimeScore,
        ];
    }

    /**
     * Decision #3: project participation derives from task activity on
     * projects the employee is actively assigned to — no new per-project
     * effort-tracking table.
     */
    private function scoreProjectParticipation(int $userId, int $tenantId, string $date): array
    {
        $activeProjectIds = ProjectAssign::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 1)
            ->pluck('project_id');

        if ($activeProjectIds->isEmpty()) {
            return ['assigned' => 0, 'completed' => 0, 'on_time' => 0, 'score' => null];
        }

        $assigned = TaskAssign::withoutGlobalScopes()
            ->join('tasks', 'tasks.id', '=', 'task_assigns.task_id')
            ->where('task_assigns.tenant_id', $tenantId)
            ->where('task_assigns.assigned_to', $userId)
            ->whereDate('tasks.task_date', $date)
            ->whereIn('tasks.project_id', $activeProjectIds)
            ->select('task_assigns.individual_status', 'task_assigns.completed_at', 'task_assigns.updated_at', 'tasks.deadline_date as task_deadline_date')
            ->get();

        $assignedCount = $assigned->count();
        if ($assignedCount === 0) {
            return ['assigned' => 0, 'completed' => 0, 'on_time' => 0, 'score' => null];
        }

        $completed = $assigned->where('individual_status', 'completed');
        $completedCount = $completed->count();
        $onTimeCount = 0;
        foreach ($completed as $a) {
            if ($this->completedOnTime($a->completed_at ?? $a->updated_at, $a->task_deadline_date)) {
                $onTimeCount++;
            }
        }

        $completionRatio = $completedCount / $assignedCount;
        $onTimeRatio = $completedCount > 0 ? ($onTimeCount / $completedCount) : 1.0;
        $score = round((($completionRatio + $onTimeRatio) / 2) * 100, 2);

        return ['assigned' => $assignedCount, 'completed' => $completedCount, 'on_time' => $onTimeCount, 'score' => $score];
    }

    private function completedOnTime($completedAt, $deadlineDate): bool
    {
        if (! $deadlineDate) {
            return true;
        }

        return Carbon::parse($completedAt)->lte(Carbon::parse($deadlineDate)->endOfDay());
    }
}
