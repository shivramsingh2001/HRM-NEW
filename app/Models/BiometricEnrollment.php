<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiometricEnrollment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'device_user_id' => 'integer',
        'synced_at' => 'datetime',
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
