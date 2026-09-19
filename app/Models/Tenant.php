<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_name',
        'subdomain',
        'custom_domain',
        'email',
        'phone',
        'logo',
        'address',
        'city',
        'state',
        'country',
        'pincode',
        'gst_number',
        'pan_number',
        'timezone',
        'date_format',
        'time_format',
        'currency',
        'currency_symbol',
        'week_start',
        'status',
        'subscription_plan',
        'max_employees',
        'settings',
        'leaves',
        'notice_period',
        'late_halfday_enabled',
        'monthly_late_allowance',
        'custom_shifts_enabled',
        'allow_multiple_punches',
        'default_shift_id',
        'default_weekoff_days',
        'field_tracking_enabled',
        'field_tracking_seats',
        'field_tracking_ping_seconds',
        'field_tracking_retention_days',
        'created_by'
    ];

    protected $casts = [
        'settings' => 'array',
        'deleted_at' => 'datetime',
        'late_halfday_enabled' => 'boolean',
        'custom_shifts_enabled' => 'boolean',
        'allow_multiple_punches' => 'boolean',
        'default_weekoff_days' => 'array',
        'field_tracking_enabled' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function designations()
    {
        return $this->hasMany(Designation::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }
}