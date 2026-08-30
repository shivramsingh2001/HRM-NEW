<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class JobOffer extends Model
{
     use TenantTrait;
    protected $table = 'job_offers';

    protected $fillable = [
        'tenant_id',
        'offer_code',
        'job_application_id',
        'candidate_id',
        'offer_date',
        'joining_date',
        'designation_id',
        'department_id',
        'reporting_head',
        'employment_type',
        'offer_status',
        'offered_ctc',
        'basic_salary',
        'hra',
        'other_allowances',
        'variable_pay',
        'offer_letter_url',
        'acceptance_date',
        'rejection_reason',
        'negotiation_details',
        'sent_at',
        'created_by'
    ];

    protected $casts = [
        'offer_date' => 'date',
        'joining_date' => 'date',
        'acceptance_date' => 'date',
        'offered_ctc' => 'decimal:2',
        'basic_salary' => 'decimal:2',
        'hra' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'variable_pay' => 'decimal:2',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Offer status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';
    const STATUS_WITHDRAWN = 'withdrawn';

    // Employment type constants
    const EMPLOYMENT_FULL_TIME = 'full_time';
    const EMPLOYMENT_PART_TIME = 'part_time';
    const EMPLOYMENT_CONTRACT = 'contract';
    const EMPLOYMENT_INTERNSHIP = 'internship';

    public static $statuses = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SENT => 'Sent',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_WITHDRAWN => 'Withdrawn'
    ];

    public static $employmentTypes = [
        self::EMPLOYMENT_FULL_TIME => 'Full Time',
        self::EMPLOYMENT_PART_TIME => 'Part Time',
        self::EMPLOYMENT_CONTRACT => 'Contract',
        self::EMPLOYMENT_INTERNSHIP => 'Internship'
    ];

    // Relationships
    public function application()
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function reportingHead()
    {
        return $this->belongsTo(User::class, 'reporting_head');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function onboarding()
    {
        return $this->hasOne(OnboardingAssignment::class);
    }

    // Accessors
    public function getOfferStatusLabelAttribute()
    {
        return self::$statuses[$this->offer_status] ?? ucfirst($this->offer_status);
    }

    public function getEmploymentTypeLabelAttribute()
    {
        return self::$employmentTypes[$this->employment_type] ?? ucfirst(str_replace('_', ' ', $this->employment_type));
    }

    public function getTotalCompensationAttribute()
    {
        $total = $this->offered_ctc;
        
        if ($this->variable_pay) {
            $total += $this->variable_pay;
        }
        
        return $total;
    }

    public function getMonthlyGrossAttribute()
    {
        return round($this->offered_ctc / 12, 2);
    }

    public function getIsAcceptedAttribute()
    {
        return $this->offer_status === self::STATUS_ACCEPTED;
    }

    public function getIsRejectedAttribute()
    {
        return $this->offer_status === self::STATUS_REJECTED;
    }

    // Scopes
    public function scopeSent($query)
    {
        return $query->where('offer_status', self::STATUS_SENT);
    }

    public function scopeAccepted($query)
    {
        return $query->where('offer_status', self::STATUS_ACCEPTED);
    }

    public function scopePendingResponse($query)
    {
        return $query->where('offer_status', self::STATUS_SENT);
    }

    // Helper methods
    public function send()
    {
        $this->update([
            'offer_status' => self::STATUS_SENT,
            'sent_at' => now()
        ]);
        
        $this->application->moveToStage(JobApplication::STAGE_OFFER_RELEASED, 
            "Offer letter sent to candidate");
    }

    public function accept($negotiationDetails = null)
    {
        $this->update([
            'offer_status' => self::STATUS_ACCEPTED,
            'acceptance_date' => now(),
            'negotiation_details' => $negotiationDetails ?? $this->negotiation_details
        ]);
        
        $this->application->moveToStage(JobApplication::STAGE_OFFER_ACCEPTED, 
            "Candidate accepted the offer");
    }

    public function reject($reason)
    {
        $this->update([
            'offer_status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason
        ]);
        
        $this->application->moveToStage(JobApplication::STAGE_OFFER_REJECTED, 
            "Candidate rejected the offer: {$reason}");
    }

    public function withdraw($reason)
    {
        $this->update([
            'offer_status' => self::STATUS_WITHDRAWN,
            'rejection_reason' => $reason
        ]);
    }
}