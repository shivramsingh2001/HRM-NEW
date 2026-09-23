<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per employee per calendar day — the daily performance "fact"
 * table. See App\Services\Performance\PerformanceDailyScorer (writer) and
 * PerformanceRollupService (daily -> weekly/monthly aggregation).
 *
 * Component score columns are nullable: null means "genuinely inapplicable
 * that day", never confused with an earned 0 — see
 * PerformanceScoreCalculator::blend().
 */
class EmployeeDailyPerformance extends Model
{
    use TenantTrait;

    protected $table = 'employee_daily_performance';

    protected $guarded = [];

    protected $casts = [
        'performance_date' => 'date',
        'policy_effective_from' => 'date',
        'calculated_at' => 'datetime',
        'worked_hours' => 'float',
        'is_late' => 'boolean',
        'is_early_departure' => 'boolean',
        'is_unauthorized_absent' => 'boolean',
        'attendance_score' => 'float',
        'task_completion_score' => 'float',
        'task_ontime_score' => 'float',
        'project_participation_score' => 'float',
        'regularization_score' => 'float',
        'overall_daily_score' => 'float',
        'components_included' => 'array',
        'calculation_audit' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCalculated($query)
    {
        return $query->where('calculation_status', 'calculated');
    }
}
