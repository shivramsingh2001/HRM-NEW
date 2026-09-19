<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class OnboardingTaskItem extends Model
{
    use TenantTrait;

    protected $table = 'onboarding_task_items';

    protected $fillable = [
        'tenant_id',
        'onboarding_assignment_id',
        'onboarding_task_id',
        'status',
        'assigned_to',
        'completed_by',
        'completed_at',
        'due_date',
        'remarks',
        'attachment_url',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_OVERDUE = 'overdue';

    public static $statuses = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_SKIPPED => 'Skipped',
        self::STATUS_OVERDUE => 'Overdue',
    ];

    // Relationships
    public function assignment()
    {
        return $this->belongsTo(OnboardingAssignment::class, 'onboarding_assignment_id');
    }

    public function task()
    {
        return $this->belongsTo(OnboardingTask::class, 'onboarding_task_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    // Helper methods
    public function markCompleted($remarks = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_by' => auth()->id(),
            'completed_at' => now(),
            'remarks' => $remarks ?? $this->remarks,
        ]);
    }

    public function markSkipped($remarks = null)
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'completed_by' => auth()->id(),
            'completed_at' => now(),
            'remarks' => $remarks ?? $this->remarks,
        ]);
    }
}
