<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class TaskApproval extends Model
{
    use TenantTrait;
    protected $guarded = [];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who approved this task
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Alternative: Get the approver user
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who created/reviewed the approval
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by')->orderBy('created_at', 'desc');
    }
}
