<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
use App\Models\User;

class JobApplication extends Model
{
    use TenantTrait;
    protected $table = 'job_applications';

    protected $fillable = [
        'tenant_id',
        'application_code',
        'job_opening_id',
        'candidate_id',
        'source',
        'applied_date',
        'expected_salary',
        'cover_letter',
        'current_stage',
        'status',
        'notes'
    ];

    protected $casts = [
        'expected_salary' => 'decimal:2',
        'applied_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Stage constants
    const STAGE_APPLICATION_RECEIVED = 'application_received';
    const STAGE_CV_SHORTLISTED = 'cv_shortlisted';
    const STAGE_CV_REJECTED = 'cv_rejected';
    const STAGE_INTERVIEW_SCHEDULED = 'interview_scheduled';
    const STAGE_INTERVIEW_COMPLETED = 'interview_completed';
    const STAGE_OFFER_RELEASED = 'offer_released';
    const STAGE_OFFER_ACCEPTED = 'offer_accepted';
    const STAGE_OFFER_REJECTED = 'offer_rejected';
    const STAGE_HIRED = 'hired';
    const STAGE_REJECTED = 'rejected';
    const STAGE_ONBOARDED = 'onboarded';

    // Source constants
    const SOURCE_INTERNAL = 'internal';
    const SOURCE_EXTERNAL = 'external';
    const SOURCE_WALKIN = 'walkin';
    const SOURCE_REFERRAL = 'referral';

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_ARCHIVED = 'archived';

    public static $stages = [
        self::STAGE_APPLICATION_RECEIVED => 'Application Received',
        self::STAGE_CV_SHORTLISTED => 'CV Shortlisted',
        self::STAGE_CV_REJECTED => 'CV Rejected',
        self::STAGE_INTERVIEW_SCHEDULED => 'Interview Scheduled',
        self::STAGE_INTERVIEW_COMPLETED => 'Interview Completed',
        self::STAGE_OFFER_RELEASED => 'Offer Released',
        self::STAGE_OFFER_ACCEPTED => 'Offer Accepted',
        self::STAGE_OFFER_REJECTED => 'Offer Rejected',
        self::STAGE_HIRED => 'Hired',
        self::STAGE_REJECTED => 'Rejected',
        self::STAGE_ONBOARDED => 'Onboarded'
    ];

    public static $sources = [
        self::SOURCE_INTERNAL => 'Internal',
        self::SOURCE_EXTERNAL => 'External',
        self::SOURCE_WALKIN => 'Walk-in',
        self::SOURCE_REFERRAL => 'Referral'
    ];

    // Relationships
    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobOpening()
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function latestInterview()
    {
        return $this->hasOne(Interview::class)->latest();
    }

    public function completedInterviews()
    {
        return $this->hasMany(Interview::class)->where('status', Interview::STATUS_COMPLETED);
    }

    public function scheduledInterviews()
    {
        return $this->hasMany(Interview::class)->where('status', Interview::STATUS_SCHEDULED);
    }

    public function offer()
    {
        return $this->hasOne(JobOffer::class);
    }

    public function logs()
    {
        return $this->hasMany(RecruitmentWorkflowLog::class);
    }

    // Accessors
    public function getCurrentStageLabelAttribute()
    {
        return self::$stages[$this->current_stage] ?? ucfirst(str_replace('_', ' ', $this->current_stage));
    }

    public function getSourceLabelAttribute()
    {
        return self::$sources[$this->source] ?? ucfirst($this->source);
    }

    public function getIsShortlistedAttribute()
    {
        return $this->current_stage === self::STAGE_CV_SHORTLISTED;
    }

    public function getIsRejectedAttribute()
    {
        return in_array($this->current_stage, [self::STAGE_CV_REJECTED, self::STAGE_REJECTED]);
    }

    public function getIsOfferedAttribute()
    {
        return in_array($this->current_stage, [self::STAGE_OFFER_RELEASED, self::STAGE_OFFER_ACCEPTED]);
    }

    public function getIsOnboardedAttribute()
    {
        return $this->current_stage === self::STAGE_ONBOARDED;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByStage($query, $stage)
    {
        return $query->where('current_stage', $stage);
    }

    public function scopeShortlisted($query)
    {
        return $query->where('current_stage', self::STAGE_CV_SHORTLISTED);
    }

    public function scopeInterviewing($query)
    {
        return $query->whereIn('current_stage', [self::STAGE_INTERVIEW_SCHEDULED, self::STAGE_INTERVIEW_COMPLETED]);
    }

    // Helper methods
    public function moveToStage($newStage, $remarks = null)
    {
        $oldStage = $this->current_stage;
        
        $this->update(['current_stage' => $newStage]);

        // Update candidate status based on application stage
        $this->updateCandidateStatus($newStage);

        // Log the stage change
        RecruitmentWorkflowLog::create([
            'tenant_id' => $this->tenant_id,
            'job_application_id' => $this->id,
            'from_stage' => $oldStage,
            'to_stage' => $newStage,
            'action' => 'stage_changed',
            'action_by' => auth()->id(),
            'remarks' => $remarks
        ]);

        return true;
    }

    protected function updateCandidateStatus($stage)
    {
        $candidateStatusMap = [
            self::STAGE_APPLICATION_RECEIVED => Candidate::STATUS_NEW,
            self::STAGE_CV_SHORTLISTED => Candidate::STATUS_SCREENING,
            self::STAGE_INTERVIEW_SCHEDULED => Candidate::STATUS_INTERVIEWING,
            self::STAGE_INTERVIEW_COMPLETED => Candidate::STATUS_INTERVIEWING,
            self::STAGE_OFFER_RELEASED => Candidate::STATUS_OFFERED,
            self::STAGE_OFFER_ACCEPTED => Candidate::STATUS_OFFERED,
            self::STAGE_ONBOARDED => Candidate::STATUS_ONBOARDED,
            self::STAGE_CV_REJECTED => Candidate::STATUS_REJECTED,
            self::STAGE_REJECTED => Candidate::STATUS_REJECTED,
            self::STAGE_OFFER_REJECTED => Candidate::STATUS_REJECTED
        ];

        if (isset($candidateStatusMap[$stage])) {
            $this->candidate->update(['status' => $candidateStatusMap[$stage]]);
        }
    }

    public function canScheduleInterview()
    {
        return in_array($this->current_stage, [
            self::STAGE_CV_SHORTLISTED,
            self::STAGE_INTERVIEW_COMPLETED
        ]);
    }

    public function canReleaseOffer()
    {
        return $this->current_stage === self::STAGE_INTERVIEW_COMPLETED;
    }
    
}