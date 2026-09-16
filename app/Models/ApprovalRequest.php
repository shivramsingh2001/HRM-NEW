<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'current_level' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function actions()
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('id');
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function subject()
    {
        return $this->morphTo(null, 'subject_type', 'subject_id');
    }
}
