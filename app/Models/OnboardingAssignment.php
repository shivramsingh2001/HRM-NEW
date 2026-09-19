<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class OnboardingAssignment extends Model
{
    use TenantTrait;

    protected $table = 'onboarding_assignments';

    protected $fillable = [
        'tenant_id',
        'assignment_code',
        'candidate_id',
        'job_offer_id',
        'user_id',
        'onboarding_status',
        'start_date',
        'expected_completion_date',
        'actual_completion_date',
        'employee_id_generated',
        'welcome_email_sent',
        'onboarding_buddy',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'welcome_email_sent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_NOT_STARTED = 'not_started';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static $statuses = [
        self::STATUS_NOT_STARTED => 'Not Started',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    // Relationships
    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobOffer()
    {
        return $this->belongsTo(JobOffer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function onboardingBuddy()
    {
        return $this->belongsTo(User::class, 'onboarding_buddy');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function taskItems()
    {
        return $this->hasMany(OnboardingTaskItem::class);
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->onboarding_status] ?? ucfirst(str_replace('_', ' ', $this->onboarding_status));
    }

    public function getIsCompletedAttribute()
    {
        return $this->onboarding_status === self::STATUS_COMPLETED;
    }

    public function getMandatoryTasksCompleteAttribute()
    {
        return $this->taskItems()
            ->whereHas('task', fn ($q) => $q->where('is_mandatory', true))
            ->where('status', '!=', OnboardingTaskItem::STATUS_COMPLETED)
            ->where('status', '!=', OnboardingTaskItem::STATUS_SKIPPED)
            ->doesntExist();
    }

    // Helper methods
    public function markCompleted()
    {
        $this->update([
            'onboarding_status' => self::STATUS_COMPLETED,
            'actual_completion_date' => now(),
        ]);
    }
}
