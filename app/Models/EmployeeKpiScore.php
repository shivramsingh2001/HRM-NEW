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
        
        // JSON details
        'attendance_details',
        'leave_details',
        'task_details',
        'regularization_details',
        'calculation_audit',
        
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
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Calculate overall score using weighted average
     */
    public function getOverallScoreAttribute(): float
    {
        // Use stored overall score if available
        if ($this->attributes['overall_score'] ?? null) {
            return (float) $this->attributes['overall_score'];
        }
        
        // Calculate based on available scores
        $weights = [
            'attendance' => 35,
            'task_completion' => 35,
            'deadline_met' => 15,
            'regularization' => 5,
            'manager_rating' => 10,
        ];
        
        $totalWeight = 0;
        $weightedSum = 0;
        
        if ($this->attendance_score) {
            $totalWeight += $weights['attendance'];
            $weightedSum += $this->attendance_score * ($weights['attendance'] / 100);
        }
        
        if ($this->task_completion_score) {
            $totalWeight += $weights['task_completion'];
            $weightedSum += $this->task_completion_score * ($weights['task_completion'] / 100);
        }
        
        if ($this->deadline_met_score) {
            $totalWeight += $weights['deadline_met'];
            $weightedSum += $this->deadline_met_score * ($weights['deadline_met'] / 100);
        }
        
        if ($this->regularization_score && $this->regularization_score > 0) {
            $totalWeight += $weights['regularization'];
            $weightedSum += $this->regularization_score * ($weights['regularization'] / 100);
        }
        
        if ($this->manager_rating_score && $this->manager_rating_score > 0) {
            $totalWeight += $weights['manager_rating'];
            $weightedSum += $this->manager_rating_score * ($weights['manager_rating'] / 100);
        }
        
        if ($totalWeight == 0) {
            return 0;
        }
        
        return round(($weightedSum / ($totalWeight / 100)), 2);
    }
    
    /**
     * Get grade based on overall score
     */
    public function getGradeAttribute(): string
    {
        // Use stored grade if available
        if ($this->attributes['grade'] ?? null) {
            return $this->attributes['grade'];
        }
        
        $score = $this->overall_score;
        
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'A-';
        if ($score >= 75) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 65) return 'B-';
        if ($score >= 60) return 'C+';
        if ($score >= 55) return 'C';
        if ($score >= 50) return 'C-';
        if ($score >= 45) return 'D';
        return 'F';
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