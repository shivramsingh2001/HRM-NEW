<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant-versioned performance-scoring policy (weights + penalty rules).
 * Resolved through App\Services\Performance\PerformancePolicyResolver; do
 * not query this directly from scorers.
 */
class PerformancePolicy extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date',
        'weight_attendance' => 'float',
        'weight_task_completion' => 'float',
        'weight_task_ontime' => 'float',
        'weight_project_participation' => 'float',
        'weight_regularization' => 'float',
        'weight_manager_rating' => 'float',
        'late_penalty_per_incident' => 'float',
        'late_penalty_cap' => 'float',
        'early_departure_penalty_per_incident' => 'float',
        'early_departure_penalty_cap' => 'float',
        'regularization_penalty_approved' => 'float',
        'regularization_penalty_rejected' => 'float',
        'regularization_penalty_pending' => 'float',
        'regularization_penalty_cap' => 'float',
        'task_overdue_penalty_per_task' => 'float',
        'task_overdue_penalty_cap' => 'float',
        'late_grace_minutes' => 'integer',
        'early_departure_grace_minutes' => 'integer',
        'min_tasks_for_task_score' => 'integer',
        'metadata' => 'array',
    ];
}
