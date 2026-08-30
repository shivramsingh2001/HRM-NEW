<?php
// app/Models/ManagerPerformanceReview.php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class ManagerPerformanceReview extends Model
{
    use TenantTrait;
    protected $table = 'manager_performance_reviews';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'reviewer_id',
        'kpi_score_id',
        'review_month',
        'overall_rating',
        'strengths',
        'areas_for_improvement',
        'achievements',
        'goals_next_month',
        'additional_feedback',
        'status',
        'submitted_at',
        'employee_acknowledged_at',
    ];
    
    protected $casts = [
        'review_month' => 'date',
        'submitted_at' => 'datetime',
        'employee_acknowledged_at' => 'datetime',
        'overall_rating' => 'decimal:2',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
    
    public function kpiScore()
    {
        return $this->belongsTo(EmployeeKpiScore::class, 'kpi_score_id');
    }
}