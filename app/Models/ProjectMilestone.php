<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class ProjectMilestone extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'title',
        'description',
        'due_date',
        'status',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'completed'
            && $this->due_date
            && \Carbon\Carbon::today()->gt($this->due_date->copy()->startOfDay());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'pending')->orderBy('due_date');
    }
}
