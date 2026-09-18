<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class ProjectAssign extends Model
{
     use TenantTrait;
    protected $fillable = ['tenant_id', 'project_id', 'user_id', 'is_head', 'status'];

    protected $casts = [
        'is_head' => 'boolean',
        'status' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @deprecated kept as an alias — several views/queries still call ->users on this pivot. */
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Scope for project heads
     */
    public function scopeHeads($query)
    {
        return $query->where('is_head', '1');
    }

    /**
     * Scope for active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }
}
