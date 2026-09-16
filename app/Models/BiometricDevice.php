<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiometricDevice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_provision' => 'boolean',
        'default_privilege' => 'integer',
        'last_seen_at' => 'datetime',
        'last_punch_at' => 'datetime',
    ];

    public function enrollments()
    {
        return $this->hasMany(BiometricEnrollment::class);
    }

    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }
}
