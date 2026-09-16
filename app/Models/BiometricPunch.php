<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiometricPunch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'punched_at' => 'datetime',
        'punched_at_utc' => 'datetime',
        'processed_at' => 'datetime',
        'temperature' => 'decimal:1',
        'payload' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
