<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Department;
use App\Models\Designation;
use App\Traits\TenantTrait;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOpening extends Model
{
    use TenantTrait, SoftDeletes;

    protected $table = 'job_openings';

    protected $fillable = [
        'tenant_id',
        'job_code',
        'title',
        'department_id',
        'designation_id',
        'employment_type',
        'experience_required',
        'qualification_required',
        'skills_required',
        'description',
        'responsibilities',
        'requirements',
        'location',
        'salary_range_min',
        'salary_range_max',
        'no_of_vacancies',
        'hiring_lead',
        'status',
        'published_date',
        'closed_date',
        'close_reason',
        'created_by'
    ];

    protected $casts = [
        'salary_range_min' => 'decimal:2',
        'salary_range_max' => 'decimal:2',
        'no_of_vacancies' => 'integer',
        'published_date' => 'date',
        'closed_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Employment type constants
    const EMPLOYMENT_FULL_TIME = 'full_time';
    const EMPLOYMENT_PART_TIME = 'part_time';
    const EMPLOYMENT_CONTRACT = 'contract';
    const EMPLOYMENT_INTERNSHIP = 'internship';
    const EMPLOYMENT_TEMPORARY = 'temporary';

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_CLOSED = 'closed';
    const STATUS_ON_HOLD = 'on_hold';

    public static $employmentTypes = [
        self::EMPLOYMENT_FULL_TIME => 'Full Time',
        self::EMPLOYMENT_PART_TIME => 'Part Time',
        self::EMPLOYMENT_CONTRACT => 'Contract',
        self::EMPLOYMENT_INTERNSHIP => 'Internship',
        self::EMPLOYMENT_TEMPORARY => 'Temporary'
    ];

    public static $statuses = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_ON_HOLD => 'On Hold'
    ];

    // Relationships
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function hiringLead()
    {
        return $this->belongsTo(User::class, 'hiring_lead');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PUBLISHED, self::STATUS_DRAFT]);
    }

    // Accessors
    public function getSalaryRangeAttribute()
    {
        if ($this->salary_range_min && $this->salary_range_max) {
            return $this->salary_range_min . ' - ' . $this->salary_range_max;
        } elseif ($this->salary_range_min) {
            return 'From ' . $this->salary_range_min;
        } elseif ($this->salary_range_max) {
            return 'Up to ' . $this->salary_range_max;
        }
        return 'Not disclosed';
    }

    public function getEmploymentTypeLabelAttribute()
    {
        return self::$employmentTypes[$this->employment_type] ?? ucfirst(str_replace('_', ' ', $this->employment_type));
    }

    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getSkillsArrayAttribute()
    {
        if ($this->skills_required) {
            return array_map('trim', explode(',', $this->skills_required));
        }
        return [];
    }

    // Helper methods
    public function publish()
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_date' => now()
        ]);
    }

    public function close($reason = null)
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_date' => now()
        ]);
    }

    public function getApplicationsCountAttribute()
    {
        return $this->applications()->count();
    }

    public function getShortlistedCountAttribute()
    {
        return $this->applications()->where('current_stage', JobApplication::STAGE_CV_SHORTLISTED)->count();
    }

    public function getFilledVacanciesAttribute()
    {
        return $this->applications()->where('current_stage', JobApplication::STAGE_ONBOARDED)->count();
    }

    public function getRemainingVacanciesAttribute()
    {
        return max(0, $this->no_of_vacancies - $this->filled_vacancies);
    }
}