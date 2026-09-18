<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Project extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'project_code',
        'name',
        'description',
        'start_date',
        'deadline_date',
        'project_head',
        'status',
        'priority',
        'budget',
        'progress_percentage',
        'progress_manual_override',
    ];

    protected $casts = [
        'start_date' => 'date',
        'deadline_date' => 'date',
        'progress_percentage' => 'integer',
        'progress_manual_override' => 'boolean',
        'budget' => 'decimal:2',
    ];

    /** Project manager — kept as `project_head` at the DB/column level (see UserJobDetail's office_branch/branch_id precedent for why labels change but columns don't). */
    public function head()
    {
        return $this->belongsTo(User::class, 'project_head');
    }

    public function assigns()
    {
        return $this->hasMany(ProjectAssign::class, 'project_id');
    }

    /**
     * Get all tasks for this project
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    /**
     * Get active tasks (not completed or cancelled)
     */
    public function activeTasks()
    {
        return $this->hasMany(Task::class, 'project_id')
                    ->whereNotIn('status', ['completed', 'cancelled', 'approved']);
    }

    /**
     * Get completed tasks
     */
    public function completedTasks()
    {
        return $this->hasMany(Task::class, 'project_id')
                    ->whereIn('status', ['completed', 'approved']);
    }

    /**
     * Get pending tasks
     */
    public function pendingTasks()
    {
        return $this->hasMany(Task::class, 'project_id')
                    ->where('status', 'pending');
    }

    /**
     * Get in-progress tasks
     */
    public function inProgressTasks()
    {
        return $this->hasMany(Task::class, 'project_id')
                    ->where('status', 'in_progress');
    }

    /**
     * Get overdue tasks — calendar-date comparison (a task due "today"
     * shouldn't flip to overdue the instant any time passes past midnight).
     */
    public function overdueTasks()
    {
        return $this->hasMany(Task::class, 'project_id')
                    ->where('deadline_date', '<', \Carbon\Carbon::today())
                    ->whereNotIn('status', ['completed', 'cancelled', 'approved']);
    }

    /**
     * Get tasks by priority
     */
    public function tasksByPriority($priority)
    {
        return $this->hasMany(Task::class, 'project_id')
                    ->where('priority', $priority);
    }

    /**
     * Get high priority tasks
     */
    public function highPriorityTasks()
    {
        return $this->hasMany(Task::class, 'project_id')
                    ->whereIn('priority', ['high', 'critical']);
    }

    /**
     * Get all expenses for this project
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'project_id');
    }

    /**
     * Get approved expenses for this project
     */
    public function approvedExpenses()
    {
        return $this->hasMany(Expense::class, 'project_id')
                    ->where('status', 'approved');
    }

    /**
     * Get pending expenses for this project
     */
    public function pendingExpenses()
    {
        return $this->hasMany(Expense::class, 'project_id')
                    ->where('status', 'pending');
    }

    /**
     * Get total expense amount for this project
     */
    public function getTotalExpenseAmountAttribute()
    {
        return $this->expenses()->sum('amount');
    }

    /**
     * Get all team members assigned to this project
     */
    public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'project_assigns', 'project_id', 'user_id')
                    ->withPivot('is_head', 'status')
                    ->withTimestamps();
    }

    /**
     * Get project leads/heads (users marked as head in project_assigns)
     */
    public function leads()
    {
        return $this->belongsToMany(User::class, 'project_assigns', 'project_id', 'user_id')
                    ->wherePivot('is_head', '1')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Get regular team members (not heads)
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'project_assigns', 'project_id', 'user_id')
                    ->wherePivot('is_head', '0')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /** Human-authored structured progress reports (completed/pending/issues/next actions). */
    public function updates()
    {
        return $this->hasMany(ProjectUpdate::class, 'project_id')->latest();
    }

    public function comments()
    {
        return $this->hasMany(ProjectComment::class, 'project_id')->latest();
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_id')->latest();
    }

    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class, 'project_id')->orderBy('sort_order')->orderBy('due_date');
    }

    public function risks()
    {
        return $this->hasMany(ProjectRisk::class, 'project_id')->orderByRaw("FIELD(status,'open','mitigated','resolved','closed')")->latest();
    }

    /**
     * Check if a user is assigned to this project
     */
    public function hasUser($userId)
    {
        return $this->assigns()
                    ->where('user_id', $userId)
                    ->exists();
    }

    /**
     * Check if a user is project head
     */
    public function isUserHead($userId)
    {
        return $this->assigns()
                    ->where('user_id', $userId)
                    ->where('is_head', '1')
                    ->exists();
    }

    /**
     * Get task statistics
     */
    public function getTaskStatsAttribute()
    {
        return [
            'total' => $this->tasks()->count(),
            'pending' => $this->pendingTasks()->count(),
            'in_progress' => $this->inProgressTasks()->count(),
            'completed' => $this->completedTasks()->count(),
            'overdue' => $this->overdueTasks()->count(),
            'high_priority' => $this->highPriorityTasks()->count(),
        ];
    }

    /**
     * Get expense statistics
     */
    public function getExpenseStatsAttribute()
    {
        return [
            'total' => $this->expenses()->count(),
            'total_amount' => $this->total_expense_amount,
            'approved_amount' => $this->approvedExpenses()->sum('amount'),
            'pending_amount' => $this->pendingExpenses()->sum('amount'),
        ];
    }

    /**
     * Project progress percentage. Passthrough to the stored column, kept in
     * sync by TaskProgressObserver (or manually set when
     * progress_manual_override is true) — this used to be a second,
     * independently-recomputed implementation of the same formula; now
     * there is exactly one source of truth.
     */
    public function getProgressPercentageAttribute($value)
    {
        return (int) $value;
    }

    /**
     * Scope for projects where user is head
     */
    public function scopeWhereUserIsHead($query, $userId)
    {
        return $query->whereHas('assigns', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('is_head', '1');
        });
    }

    /**
     * Scope for projects where user is member (including head)
     */
    public function scopeWhereUserIsMember($query, $userId)
    {
        return $query->whereHas('assigns', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Scope for active projects
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['ongoing', 'pending']);
    }

    /**
     * Scope for completed projects
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for projects with deadline approaching (next 7 days) —
     * calendar-date comparison; using now() here would exclude a project
     * due "today" the moment any time passed after midnight.
     */
    public function scopeDeadlineApproaching($query)
    {
        return $query->whereBetween('deadline_date', [\Carbon\Carbon::today(), \Carbon\Carbon::today()->addDays(7)])
                    ->whereIn('status', ['ongoing', 'pending']);
    }

    /**
     * Scope for overdue projects — calendar-date comparison.
     */
    public function scopeOverdue($query)
    {
        return $query->where('deadline_date', '<', \Carbon\Carbon::today())
                    ->whereIn('status', ['ongoing', 'pending']);
    }

    /**
     * Get the project status badge color
     */
    public function getStatusBadgeAttribute()
    {
        $colors = [
            'ongoing' => 'primary',
            'pending' => 'warning',
            'hold' => 'secondary',
            'completed' => 'success',
            'cancelled' => 'danger'
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    /**
     * Get the project status text
     */
    public function getStatusTextAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

}
