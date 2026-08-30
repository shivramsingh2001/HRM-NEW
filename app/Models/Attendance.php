<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Attendance extends Model
{
    use TenantTrait;
//     protected $casts = [
//     'date' => 'date',
// ];

   
    protected $guarded = [];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(AttendanceTrack::class, 'attendance_id');
    }
}
