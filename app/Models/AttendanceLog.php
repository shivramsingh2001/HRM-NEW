<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class AttendanceLog extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
