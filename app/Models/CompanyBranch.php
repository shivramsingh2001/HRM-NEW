<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

/**
 * A company's physical/organizational branch — name, address, contact,
 * manager. Purely organizational: no lat/long/radius/geofence fields at
 * all (that's AttendanceLocation's job). One employee belongs to at most
 * one Branch (user_job_details.branch_id, nullable — optional for
 * single-location companies).
 */
class CompanyBranch extends Model
{
    use TenantTrait;

    protected $table = 'company_branches';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'branch_head',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function branchHead()
    {
        return $this->belongsTo(User::class, 'branch_head');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
