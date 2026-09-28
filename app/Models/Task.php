<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Task extends Model
{
    use TenantTrait;
    protected $guarded = [];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the assignments for this task
     */
    public function assignments()
    {
        return $this->hasMany(TaskAssign::class, 'task_id');
    }

    /**
     * Get the users assigned to this task (many-to-many relationship)
     */
    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'task_assigns', 'task_id', 'assigned_to')
            ->withPivot('assigned_by', 'status')
            ->withTimestamps();
    }

    /**
     * Get the user who created/assigned the task (through first assignment)
     */
    public function createdBy()
    {
        return $this->hasOneThrough(
            User::class,
            TaskAssign::class,
            'task_id', // Foreign key on task_assigns table
            'id',      // Foreign key on users table
            'id',      // Local key on tasks table
            'assigned_by' // Local key on task_assigns table
        )->latest();
    }

    /**
     * Get the updates for this task
     */
    public function updates()
    {
        return $this->hasMany(TaskUpdate::class, 'task_id')->orderBy('created_at', 'desc');
    }


    /**
     * Get the latest update for this task
     */
    public function latestUpdate()
    {
        return $this->hasOne(TaskUpdate::class, 'task_id')->latestOfMany();
    }

    /**
     * Check if task is overdue. Compares calendar dates, not timestamps — a
     * task due "today" stays not-overdue for the whole day; it only becomes
     * overdue once today's date is past the deadline date. (Using now()
     * against a date-only value compares against midnight of that day, so
     * any task due today would show overdue the moment any time passed.)
     */
    public function getIsOverdueAttribute()
    {
        return $this->deadline_date
            && \Carbon\Carbon::today()->gt(\Carbon\Carbon::parse($this->deadline_date)->startOfDay())
            && !in_array($this->status, ['completed', 'cancelled', 'approved']);
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute()
    {
        return file_url($this->file, 'task_document');
    }

    /**
     * Get voice file URL
     */
    public function getVoiceFileUrlAttribute()
    {
        return file_url($this->voice_file, 'task_voice');
    }

    /**
     * Scope for tasks assigned to a specific user
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->whereHas('assignments', function ($q) use ($userId) {
            $q->where('assigned_to', $userId);
        });
    }

    /**
     * Scope for tasks assigned by a specific user
     */
    public function scopeAssignedBy($query, $userId)
    {
        return $query->whereHas('assignments', function ($q) use ($userId) {
            $q->where('assigned_by', $userId);
        });
    }

    /**
     * Scope for tasks by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for tasks by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for overdue tasks — calendar-date comparison (see
     * getIsOverdueAttribute for why this can't just use now()).
     */
    public function scopeOverdue($query)
    {
        return $query->where('deadline_date', '<', \Carbon\Carbon::today())
            ->whereNotIn('status', ['completed', 'cancelled', 'approved']);
    }
    public function approvals()
    {
        return $this->hasMany(TaskApproval::class, 'task_id')->orderBy('created_at', 'desc');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at', 'desc');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class)->orderBy('created_at', 'desc');
    }
}
