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

    protected $casts = [
        'request_date' => 'date',
        'last_working_date' => 'date',
        'original_last_working_date' => 'date',
        'resignation_date' => 'date',
        'eligible_for_rehire' => 'boolean',
        'exit_interview_skipped' => 'boolean',
        'manager_review_at' => 'datetime',
        'hr_review_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'offboarding_completed_at' => 'datetime',
        'exit_interview_date' => 'datetime',
        'knowledge_transfer_completed_at' => 'datetime',
        'final_settlement_processed_at' => 'datetime',
        'settlement_finalized_at' => 'datetime',
        'full_final_settlement' => 'decimal:2',
        'settlement_computed_total' => 'decimal:2',
        'settlement_final_total' => 'decimal:2',
        'settlement_paid_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // current_stage constants (meaningful once status = approved)
    const STAGE_PENDING_APPROVAL = 'pending_approval';
    const STAGE_KNOWLEDGE_TRANSFER = 'knowledge_transfer';
    const STAGE_CLEARANCE = 'clearance';
    const STAGE_EXIT_INTERVIEW = 'exit_interview';
    const STAGE_SETTLEMENT = 'settlement';
    const STAGE_READY_TO_COMPLETE = 'ready_to_complete';
    const STAGE_COMPLETED = 'completed';
    const STAGE_REJECTED = 'rejected';
    const STAGE_CANCELLED = 'cancelled';

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

    public static $stages = [
        self::STAGE_PENDING_APPROVAL => 'Pending Approval',
        self::STAGE_KNOWLEDGE_TRANSFER => 'Knowledge Transfer',
        self::STAGE_CLEARANCE => 'Clearance',
        self::STAGE_EXIT_INTERVIEW => 'Exit Interview',
        self::STAGE_SETTLEMENT => 'Final Settlement',
        self::STAGE_READY_TO_COMPLETE => 'Ready to Complete',
        self::STAGE_COMPLETED => 'Completed',
        self::STAGE_REJECTED => 'Rejected',
        self::STAGE_CANCELLED => 'Cancelled',
    ];

    public static $reasons = [
        self::REASON_RESIGNATION => 'Resignation',
        self::REASON_RETIREMENT => 'Retirement',
        self::REASON_TERMINATION => 'Termination',
        self::REASON_CONTRACT_END => 'Contract End',
        self::REASON_MUTUAL_AGREEMENT => 'Mutual Agreement',
        self::REASON_OTHER => 'Other'
    ];

    /**
     * The request_type this reason is approved under in ApprovalService —
     * termination gets its own 1-level (HR-only) workflow, everything else
     * shares the 2-level Manager->HR workflow. See config('offboarding.reason_rules').
     */
    public function approvalRequestType(): string
    {
        return $this->reason === self::REASON_TERMINATION ? 'offboarding_termination' : 'offboarding';
    }

    /**
     * ApprovalService::subjectUser()/resolveApprovers() read `$subject->user_id`
     * on whatever model is passed as the approval subject — this table has no
     * user_id column (the employee FK is employee_id), so without this
     * accessor every reporting_head/department_head lookup silently resolves
     * to no approvers.
     */
    public function getUserIdAttribute()
    {
        return $this->employee_id;
    }

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

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function exitInterviewConductedBy()
    {
        return $this->belongsTo(User::class, 'exit_interview_conducted_by');
    }

    public function exitInterview()
    {
        return $this->hasOne(ExitInterview::class);
    }

    public function clearanceTasks()
    {
        return $this->hasMany(OffboardingClearanceTask::class);
    }

    public function settlementItems()
    {
        return $this->hasMany(OffboardingSettlementItem::class);
    }

    public function noticeOverrides()
    {
        return $this->hasMany(OffboardingNoticeOverride::class);
    }

    public function settlementFinalizedBy()
    {
        return $this->belongsTo(User::class, 'settlement_finalized_by');
    }

    /**
     * The generic engine's ApprovalRequest for this offboarding request —
     * read-only trail (level, approver, decision, timestamp); source of
     * truth for the approval stage, the mirrored manager_review_status/
     * hr_review_status columns below are denormalized copies of this.
     */
    public function approvalRequest()
    {
        return $this->morphOne(\App\Models\ApprovalRequest::class, 'subject', 'subject_type', 'subject_id')
            ->where('subject_type', self::class);
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getStageLabelAttribute()
    {
        return self::$stages[$this->current_stage] ?? ucfirst(str_replace('_', ' ', (string) $this->current_stage));
    }

    /**
     * Maps status/current_stage onto one of the color keys theme-custom.css's
     * .status-badge[data-status] actually styles (pending/approved/rejected/
     * completed/in_progress/...) — used only for badge color, getStageLabelAttribute()
     * above still supplies the real human-readable text.
     */
    public function getBadgeStatusAttribute()
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return $this->status === self::STATUS_PENDING_APPROVAL ? 'pending' : $this->status;
        }

        return match ($this->current_stage) {
            self::STAGE_COMPLETED => 'completed',
            default => 'in_progress',
        };
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

    public function scopeAtStage($query, string $stage)
    {
        return $query->where('current_stage', $stage);
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
