<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantTrait;

class AttendanceTrack extends Model
{
     use TenantTrait;
    protected $guarded = [];
    
       public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
