<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class ProjectAssign extends Model
{
     use TenantTrait;
    protected $guarded = [];
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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
