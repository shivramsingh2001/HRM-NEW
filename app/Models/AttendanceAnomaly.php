<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AttendanceAnomaly extends Model
{
    use TenantTrait;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'detail' => 'array',
        'created_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];
}
