<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class TaskUpdate extends Model
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
     * Get the user who updated the task
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for updates by a specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('updated_by', $userId);
    }

    /**
     * Scope for updates with specific status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
