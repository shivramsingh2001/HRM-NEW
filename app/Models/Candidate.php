<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use TenantTrait, SoftDeletes;

    protected $table = 'candidates';

    protected $fillable = [
        'tenant_id',
        'candidate_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'date_of_birth',
        'gender',
        'current_location',
        'preferred_location',
        'total_experience',
        'relevant_experience',
        'current_ctc',
        'expected_ctc',
        'notice_period',
        'notice_period_negotiable',
        'current_company',
        'qualification',
        'skills',
        'source',
        'source_detail',
        'referral_by',
        'resume_url',
        'resume_original_name',
        'profile_image',
        'status',
        'notes'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'current_ctc' => 'decimal:2',
        'expected_ctc' => 'decimal:2',
        'notice_period_negotiable' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Gender constants
    const GENDER_MALE = 'male';
    const GENDER_FEMALE = 'female';
    const GENDER_OTHER = 'other';

    // Source constants
    const SOURCE_CAREER_PAGE = 'career_page';
    const SOURCE_LINKEDIN = 'linkedin';
    const SOURCE_NAUKRI = 'naukri';
    const SOURCE_INDEED = 'indeed';
    const SOURCE_REFERRAL = 'referral';
    const SOURCE_AGENCY = 'agency';
    const SOURCE_WALKIN = 'walkin';
    const SOURCE_OTHER = 'other';

    // Status constants
    const STATUS_NEW = 'new';
    const STATUS_CONTACTED = 'contacted';
    const STATUS_SCREENING = 'screening';
    const STATUS_INTERVIEWING = 'interviewing';
    const STATUS_OFFERED = 'offered';
    const STATUS_ONBOARDED = 'onboarded';
    const STATUS_REJECTED = 'rejected';
    const STATUS_HIRED = 'hired';

    public static $sources = [
        self::SOURCE_CAREER_PAGE => 'Career Page',
        self::SOURCE_LINKEDIN => 'LinkedIn',
        self::SOURCE_NAUKRI => 'Naukri',
        self::SOURCE_INDEED => 'Indeed',
        self::SOURCE_REFERRAL => 'Referral',
        self::SOURCE_AGENCY => 'Agency',
        self::SOURCE_WALKIN => 'Walk-in',
        self::SOURCE_OTHER => 'Other'
    ];

    public static $statuses = [
        self::STATUS_NEW => 'New',
        self::STATUS_CONTACTED => 'Contacted',
        self::STATUS_SCREENING => 'Screening',
        self::STATUS_INTERVIEWING => 'Interviewing',
        self::STATUS_OFFERED => 'Offered',
        self::STATUS_ONBOARDED => 'Onboarded',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_HIRED => 'Hired'
    ];

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getSourceLabelAttribute()
    {
        return self::$sources[$this->source] ?? ucfirst(str_replace('_', ' ', $this->source));
    }

    public function getSkillsArrayAttribute()
    {
        if ($this->skills) {
            return array_map('trim', explode(',', $this->skills));
        }
        return [];
    }

    public function getGenderLabelAttribute()
    {
        return ucfirst($this->gender);
    }

    public function getAgeAttribute()
    {
        if ($this->date_of_birth) {
            return $this->date_of_birth->age;
        }
        return null;
    }

    // Relationships
    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function latestApplication()
    {
        return $this->hasOne(JobApplication::class)->latest();
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function latestInterview()
    {
        return $this->hasOne(Interview::class)->latest();
    }

    public function offers()
    {
        return $this->hasMany(JobOffer::class);
    }

    public function latestOffer()
    {
        return $this->hasOne(JobOffer::class)->latest();
    }

    public function onboarding()
    {
        return $this->hasOne(OnboardingAssignment::class);
    }

    public function documents()
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function referralBy()
    {
        return $this->belongsTo(User::class, 'referral_by');
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }

    // Scopes
    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeInterviewing($query)
    {
        return $query->where('status', self::STATUS_INTERVIEWING);
    }

    public function scopeOffered($query)
    {
        return $query->where('status', self::STATUS_OFFERED);
    }

    // Helper methods
    public function updateStatus($newStatus, $notes = null)
    {
        $this->update([
            'status' => $newStatus,
            'notes' => $notes ?? $this->notes
        ]);
    }

    public function hasAppliedFor($jobOpeningId)
    {
        return $this->applications()->where('job_opening_id', $jobOpeningId)->exists();
    }

    public function getTotalYearsExperienceAttribute()
    {
        if ($this->total_experience) {
            preg_match('/(\d+)/', $this->total_experience, $matches);
            return $matches[1] ?? 0;
        }
        return 0;
    }
}