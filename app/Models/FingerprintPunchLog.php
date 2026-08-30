<?php
// app/Models/FingerprintPunchLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintPunchLog extends Model
{
    protected $fillable = [
        'serial_number',
        'device_user_id',
        'user_id',
        'tenant_id',
        'punch_type',
        'input_type',
        'temperature',
        'face_mask',
        'log_time',
        'operation_id',
        'raw_payload',
        'processed',
        'process_error',
    ];

    protected $casts = [
        'log_time' => 'datetime',
        'temperature' => 'decimal:1',
        'face_mask' => 'boolean',
        'raw_payload' => 'array',
        'processed' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'serial_number', 'serial_number');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }
}