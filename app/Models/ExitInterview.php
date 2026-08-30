<?php
// app/Models/ExitInterview.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExitInterview extends Model
{
    protected $table = 'exit_interviews';

    protected $fillable = [
        'tenant_id',
        'offboarding_request_id',
        'employee_id',
        'interviewer_id',
        'interview_date',
        'work_environment_rating',
        'management_rating',
        'career_growth_rating',
        'compensation_rating',
        'work_life_balance_rating',
        'primary_reason',
        'what_would_improve',
        'would_recommend',
        'feedback_comments',
        'suggestions',
        'created_by'
    ];

    protected $casts = [
        'interview_date' => 'datetime',
        'work_environment_rating' => 'integer',
        'management_rating' => 'integer',
        'career_growth_rating' => 'integer',
        'compensation_rating' => 'integer',
        'work_life_balance_rating' => 'integer',
        'would_recommend' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function offboardingRequest()
    {
        return $this->belongsTo(OffboardingRequest::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function interviewer()
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessors
    public function getAverageRatingAttribute()
    {
        $ratings = [
            $this->work_environment_rating,
            $this->management_rating,
            $this->career_growth_rating,
            $this->compensation_rating,
            $this->work_life_balance_rating
        ];
        
        $validRatings = array_filter($ratings);
        
        if (count($validRatings) > 0) {
            return round(array_sum($validRatings) / count($validRatings), 2);
        }
        
        return null;
    }

    public function getWouldRecommendLabelAttribute()
    {
        if ($this->would_recommend === null) return 'Not Specified';
        return $this->would_recommend ? 'Yes' : 'No';
    }

    // Scopes
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByInterviewer($query, $interviewerId)
    {
        return $query->where('interviewer_id', $interviewerId);
    }

    public function scopeHighRisk($query)
    {
        return $query->where('work_environment_rating', '<=', 2)
            ->orWhere('management_rating', '<=', 2)
            ->orWhere('career_growth_rating', '<=', 2);
    }
}