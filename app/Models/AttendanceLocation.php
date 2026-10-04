<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

/**
 * A geofenced check-in point for attendance (name + lat/long + radius),
 * read by mobile clock-in/out. Purely an attendance concept — organizational
 * structure lives on the separate CompanyBranch model.
 *
 * Table renamed from `branches` (2026_09_20_000001) to stop conflating this
 * with organizational Branch membership.
 */
class AttendanceLocation extends Model
{
    use TenantTrait;

    protected $table = 'attendance_locations';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'latitude',
        'longitude',
        'radius',
        'geofence_enabled',
        'status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'integer',
        'geofence_enabled' => 'boolean',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Display label for an employee's assigned attendance location
     * (user_job_details.office_branch): 0 means "any location".
     */
    public static function labelFor($officeBranch, ?string $name): string
    {
        if ($officeBranch === null || $officeBranch === '') {
            return '—';
        }
        if ((int) $officeBranch === 0) {
            return 'Any location';
        }

        return $name ?: '—';
    }
}
