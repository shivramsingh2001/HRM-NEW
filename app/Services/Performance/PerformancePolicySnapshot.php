<?php

namespace App\Services\Performance;

/**
 * An immutable, resolved performance-scoring policy for one (tenant, date).
 *
 * Every scorer consults a snapshot instead of hardcoded weights/penalties, so
 * the rules can vary per tenant and per effective_from without the scoring
 * logic being duplicated. Built by
 * App\Services\Performance\PerformancePolicyResolver.
 */
final class PerformancePolicySnapshot
{
    public function __construct(
        public readonly ?int $tenantId,
        public readonly string $effectiveFrom,
        public readonly float $weightAttendance = 30.00,
        public readonly float $weightTaskCompletion = 20.00,
        public readonly float $weightTaskOntime = 15.00,
        public readonly float $weightProjectParticipation = 10.00,
        public readonly float $weightRegularization = 10.00,
        public readonly float $weightManagerRating = 15.00,
        public readonly int $lateGraceMinutes = 10,
        public readonly float $latePenaltyPerIncident = 2.00,
        public readonly float $latePenaltyCap = 20.00,
        public readonly int $earlyDepartureGraceMinutes = 10,
        public readonly float $earlyDeparturePenaltyPerIncident = 2.00,
        public readonly float $earlyDeparturePenaltyCap = 20.00,
        public readonly float $regularizationPenaltyApproved = 2.00,
        public readonly float $regularizationPenaltyRejected = 15.00,
        public readonly float $regularizationPenaltyPending = 5.00,
        public readonly float $regularizationPenaltyCap = 30.00,
        public readonly float $taskOverduePenaltyPerTask = 5.00,
        public readonly float $taskOverduePenaltyCap = 20.00,
        public readonly int $minTasksForTaskScore = 1,
    ) {
    }

    public static function default(): self
    {
        return new self(tenantId: null, effectiveFrom: '2000-01-01');
    }

    /**
     * @param  array<string,mixed>|object  $row  a row from `performance_policies`
     */
    public static function fromRow($row): self
    {
        $r = (array) $row;
        $get = fn (string $k, $default) => array_key_exists($k, $r) && $r[$k] !== null ? $r[$k] : $default;

        return new self(
            tenantId: $r['tenant_id'] ?? null,
            effectiveFrom: (string) $get('effective_from', '2000-01-01'),
            weightAttendance: (float) $get('weight_attendance', 30.00),
            weightTaskCompletion: (float) $get('weight_task_completion', 20.00),
            weightTaskOntime: (float) $get('weight_task_ontime', 15.00),
            weightProjectParticipation: (float) $get('weight_project_participation', 10.00),
            weightRegularization: (float) $get('weight_regularization', 10.00),
            weightManagerRating: (float) $get('weight_manager_rating', 15.00),
            lateGraceMinutes: (int) $get('late_grace_minutes', 10),
            latePenaltyPerIncident: (float) $get('late_penalty_per_incident', 2.00),
            latePenaltyCap: (float) $get('late_penalty_cap', 20.00),
            earlyDepartureGraceMinutes: (int) $get('early_departure_grace_minutes', 10),
            earlyDeparturePenaltyPerIncident: (float) $get('early_departure_penalty_per_incident', 2.00),
            earlyDeparturePenaltyCap: (float) $get('early_departure_penalty_cap', 20.00),
            regularizationPenaltyApproved: (float) $get('regularization_penalty_approved', 2.00),
            regularizationPenaltyRejected: (float) $get('regularization_penalty_rejected', 15.00),
            regularizationPenaltyPending: (float) $get('regularization_penalty_pending', 5.00),
            regularizationPenaltyCap: (float) $get('regularization_penalty_cap', 30.00),
            taskOverduePenaltyPerTask: (float) $get('task_overdue_penalty_per_task', 5.00),
            taskOverduePenaltyCap: (float) $get('task_overdue_penalty_cap', 20.00),
            minTasksForTaskScore: (int) $get('min_tasks_for_task_score', 1),
        );
    }

    /** The 5 daily-grain weights, as a plain map — used by PerformanceScoreCalculator::blend(). */
    public function dailyWeights(): array
    {
        return [
            'attendance' => $this->weightAttendance,
            'task_completion' => $this->weightTaskCompletion,
            'task_ontime' => $this->weightTaskOntime,
            'project_participation' => $this->weightProjectParticipation,
            'regularization' => $this->weightRegularization,
        ];
    }

    /** The 2 monthly top-level weights (objective blend + manager rating). */
    public function monthlyWeights(): array
    {
        return [
            'objective' => 100.00 - $this->weightManagerRating,
            'manager_rating' => $this->weightManagerRating,
        ];
    }
}
