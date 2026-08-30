<?php
// app/Models/OffboardingRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;

class OffboardingRequest extends Model
{
    use TenantTrait, SoftDeletes;

    protected $table = 'offboarding_requests';

    protected $guarded = [
    
    ];

    // protected $casts = [
    //     'request_date' => 'date',
    //     'last_working_date' => 'date',
    //     'resignation_date' => 'date',
    //     'eligible_for_rehire' => 'boolean',
    //     'approved_at' => 'datetime',
    //     'completed_at' => 'datetime',
    //     'exit_interview_date' => 'datetime',
    //     'full_final_settlement' => 'decimal:2',
    //     'settlement_paid_date' => 'date',
    //     'created_at' => 'datetime',
    //     'updated_at' => 'datetime',
    //     'deleted_at' => 'datetime'
    // ];

    // Status constants
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Reason constants
    const REASON_RESIGNATION = 'resignation';
    const REASON_RETIREMENT = 'retirement';
    const REASON_TERMINATION = 'termination';
    const REASON_CONTRACT_END = 'contract_end';
    const REASON_MUTUAL_AGREEMENT = 'mutual_agreement';
    const REASON_OTHER = 'other';

    public static $statuses = [
        self::STATUS_PENDING_APPROVAL => 'Pending Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled'
    ];

    public static $reasons = [
        self::REASON_RESIGNATION => 'Resignation',
        self::REASON_RETIREMENT => 'Retirement',
        self::REASON_TERMINATION => 'Termination',
        self::REASON_CONTRACT_END => 'Contract End',
        self::REASON_MUTUAL_AGREEMENT => 'Mutual Agreement',
        self::REASON_OTHER => 'Other'
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function exitInterviewConductedBy()
    {
        return $this->belongsTo(User::class, 'exit_interview_conducted_by');
    }

    public function exitInterview()
    {
        return $this->hasOne(ExitInterview::class);
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getReasonLabelAttribute()
    {
        return self::$reasons[$this->reason] ?? ucfirst(str_replace('_', ' ', $this->reason));
    }

    public function getAssetReturnStatusLabelAttribute()
    {
        $statuses = [
            'pending' => 'Pending',
            'partial' => 'Partial',
            'completed' => 'Completed'
        ];
        return $statuses[$this->asset_return_status] ?? ucfirst($this->asset_return_status);
    }

    public function getDocumentReturnStatusLabelAttribute()
    {
        $statuses = [
            'pending' => 'Pending',
            'partial' => 'Partial',
            'completed' => 'Completed'
        ];
        return $statuses[$this->document_return_status] ?? ucfirst($this->document_return_status);
    }

    public function getClearanceStatusLabelAttribute()
    {
        $statuses = [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed'
        ];
        return $statuses[$this->clearance_status] ?? ucfirst($this->clearance_status);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }
    // In App\Models\OffboardingRequest.php

    public function managerReviewBy()
    {
        return $this->belongsTo(User::class, 'manager_review_by');
    }

    public function hrReviewBy()
    {
        return $this->belongsTo(User::class, 'hr_review_by');
    }

    public function finalSettlementProcessedBy()
    {
        return $this->belongsTo(User::class, 'final_settlement_processed_by');
    }
}
