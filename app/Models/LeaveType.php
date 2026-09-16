<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class LeaveType extends Model
{
     use TenantTrait;
    protected $guarded = [];

    /**
     * True for a system-managed type (e.g. LWP) that must not be renamed,
     * reconfigured, or deleted via the admin UI.
     */
    public function isSystemType(): bool
    {
        return !is_null($this->code);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class, 'leave_type');
    }
}
