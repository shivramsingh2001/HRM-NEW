<?php
// app/Models/FingerprintDevice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FingerprintDevice extends Model
{
    protected $fillable = [
        'serial_number',
        'auth_token',
        'label_name',
        'tenant_id',
        'branch_id',
        'last_seen_at',
        'status',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'status' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function userMaps(): HasMany
    {
        return $this->hasMany(DeviceUserMap::class);
    }

    public function punchLogs(): HasMany
    {
        return $this->hasMany(FingerprintPunchLog::class, 'serial_number', 'serial_number');
    }
}