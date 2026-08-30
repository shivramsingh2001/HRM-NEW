<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentWorkflowLog extends Model
{
   
    protected $table = 'recruitment_workflow_logs';

    protected $fillable = [
        'tenant_id',
        'job_application_id',
        'from_stage',
        'to_stage',
        'action',
        'action_by',
        'remarks',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Action constants
    const ACTION_CREATED = 'created';
    const ACTION_STAGE_CHANGED = 'stage_changed';
    const ACTION_INTERVIEW_SCHEDULED = 'interview_scheduled';
    const ACTION_INTERVIEW_COMPLETED = 'interview_completed';
    const ACTION_OFFER_SENT = 'offer_sent';
    const ACTION_OFFER_ACCEPTED = 'offer_accepted';
    const ACTION_OFFER_REJECTED = 'offer_rejected';
    const ACTION_ONBOARDING_STARTED = 'onboarding_started';
    const ACTION_ONBOARDING_COMPLETED = 'onboarding_completed';
    const ACTION_REJECTED = 'rejected';

    // Relationships
    public function jobApplication()
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    // Accessors
    public function getActionLabelAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->action));
    }

    public function getFromStageLabelAttribute()
    {
        return JobApplication::$stages[$this->from_stage] ?? $this->from_stage;
    }

    public function getToStageLabelAttribute()
    {
        return JobApplication::$stages[$this->to_stage] ?? $this->to_stage;
    }

    // Scopes
    public function scopeByApplication($query, $applicationId)
    {
        return $query->where('job_application_id', $applicationId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}