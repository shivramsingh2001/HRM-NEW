<?php
// app/Models/EmployeeKpiScore.php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeKpiScore extends Model
{
    use TenantTrait;
    protected $table = 'employee_kpi_scores';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'reporting_month',
        
        // Core scores
        'attendance_score',
        'task_completion_score',
        'deadline_met_score',
        'regularization_score',
        'manager_rating_score',
        'overall_score',
        'grade',
        
        // Attendance breakdown
        'present_days',
        'absent_days',
        'half_days',
        'late_days',
        'early_departure_days',
        'paid_leaves',
        'unpaid_leaves',
        'holidays',
        'weekoffs',
        
        // Task metrics
        'assigned_tasks',
        'completed_tasks',
        'on_time_completed_tasks',
        'late_completed_tasks',
        
        // Regularization metrics
        'regularization_count',
        'approved_regularization_count',
        'rejected_regularization_count',
        'pending_regularization_count',
        
        // Late tracking
        'late_count',
        'total_late_minutes',
        'late_penalty',
        
        // Overtime & Payroll
        'overtime_hours',
        'payroll_cost',
        
        // Manager rating details
        'manager_rating_raw',
        'manager_feedback',
        'manager_rated_by',
        'manager_rated_at',
        'manager_rating_included',

        // Project participation (added for the daily-scoring redesign)
        'project_participation_score',
        'project_assigned_tasks',
        'project_completed_tasks',
        'project_on_time_tasks',
        'overdue_tasks',

        // Rollup completeness / policy audit
        'working_days_in_period',
        'days_calculated',
        'days_expected',
        'policy_effective_from',

        // JSON details
        'attendance_details',
        'leave_details',
        'task_details',
        'regularization_details',
        'calculation_audit',
        'weights_snapshot',

        // Remarks & Status
        'remarks',
        'status',
        'calculated_at',
        'reviewed_at',
        'approved_at',

        // Legacy fields (if still needed)
        'quality_score',
        'collaboration_score',
    ];
    
    protected $casts = [
        'reporting_month' => 'date',
        'calculated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'attendance_score' => 'decimal:2',
        'task_completion_score' => 'decimal:2',
        'deadline_met_score' => 'decimal:2',
        'regularization_score' => 'decimal:2',
        'manager_rating_score' => 'decimal:2',
        'overall_score' => 'decimal:2',
        'late_penalty' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'payroll_cost' => 'decimal:2',
        'manager_rating_raw' => 'decimal:2',
        'present_days' => 'integer',
        'absent_days' => 'integer',
        'half_days' => 'integer',
        'late_days' => 'integer',
        'early_departure_days' => 'integer',
        'paid_leaves' => 'integer',
        'unpaid_leaves' => 'integer',
        'holidays' => 'integer',
        'weekoffs' => 'integer',
        'assigned_tasks' => 'integer',
        'completed_tasks' => 'integer',
        'on_time_completed_tasks' => 'integer',
        'late_completed_tasks' => 'integer',
        'regularization_count' => 'integer',
        'approved_regularization_count' => 'integer',
        'rejected_regularization_count' => 'integer',
        'pending_regularization_count' => 'integer',
        'late_count' => 'integer',
        'total_late_minutes' => 'integer',
        'attendance_details' => 'array',
        'leave_details' => 'array',
        'task_details' => 'array',
        'regularization_details' => 'array',
        'calculation_audit' => 'array',
        'weights_snapshot' => 'array',
        'project_participation_score' => 'decimal:2',
        'project_assigned_tasks' => 'integer',
        'project_completed_tasks' => 'integer',
        'project_on_time_tasks' => 'integer',
        'overdue_tasks' => 'integer',
        'working_days_in_period' => 'integer',
        'days_calculated' => 'integer',
        'days_expected' => 'integer',
        'policy_effective_from' => 'date',
        'manager_rating_included' => 'boolean',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Scope for a specific month
     */
    public function scopeForMonth($query, $year, $month)
    {
        $date = "{$year}-{$month}-01";
        return $query->where('reporting_month', $date);
    }
    
    /**
     * Scope for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Get attendance details as array
     */
    public function getAttendanceDetailsArray(): array
    {
        if (is_string($this->attendance_details)) {
            return json_decode($this->attendance_details, true) ?? [];
        }
        return $this->attendance_details ?? [];
    }
    
    /**
     * Get task details as array
     */
    public function getTaskDetailsArray(): array
    {
        if (is_string($this->task_details)) {
            return json_decode($this->task_details, true) ?? [];
        }
        return $this->task_details ?? [];
    }
    
    /**
     * Get leave details as array
     */
    public function getLeaveDetailsArray(): array
    {
        if (is_string($this->leave_details)) {
            return json_decode($this->leave_details, true) ?? [];
        }
        return $this->leave_details ?? [];
    }
}