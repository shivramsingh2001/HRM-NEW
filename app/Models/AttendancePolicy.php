<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A versioned attendance rule-set for a tenant (or the global default when
 * tenant_id is NULL). Resolved through App\Services\Attendance\PolicyResolver;
 * do not query this directly from classifiers.
 */
class AttendancePolicy extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date',
        'present_ratio' => 'float',
        'half_day_ratio' => 'float',
        'fallback_present_hours' => 'float',
        'fallback_half_hours' => 'float',
        'full_day_min_hours' => 'float',
        'overtime_after_hours' => 'float',
        'overtime_multiplier' => 'float',
        'grace_minutes' => 'integer',
        'rounding_minutes' => 'integer',
        'late_halfday_enabled' => 'boolean',
        'monthly_late_allowance' => 'integer',
        'min_rest_hours' => 'float',
        'max_daily_hours' => 'float',
        'sandwich_leave' => 'boolean',
        'metadata' => 'array',
    ];
}
