<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class TaskAssign extends Model
{
    use TenantTrait;
     protected $guarded = [];

      protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

      public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * Get the user who assigned the task
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the user to whom the task is assigned
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope for assignments by assigner
     */
    public function scopeByAssigner($query, $userId)
    {
        return $query->where('assigned_by', $userId);
    }

    /**
     * Scope for assignments to a specific user
     */
    public function scopeToUser($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }
}
