<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStep extends Model
{
    protected $guarded = [];

    protected $casts = [
        'level' => 'integer',
        'sla_hours' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }
}
