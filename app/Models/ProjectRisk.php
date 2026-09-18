<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/** A project risk or blocker — distinguished by `type`, one table for both. */
class ProjectRisk extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'type',
        'title',
        'description',
        'severity',
        'status',
        'raised_by',
        'raised_at',
        'resolved_at',
    ];

    protected $casts = [
        'raised_at' => 'date',
        'resolved_at' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'mitigated']);
    }

    public function scopeBlockers($query)
    {
        return $query->where('type', 'blocker');
    }
}
