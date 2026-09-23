<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail of every notice-period deviation on a request. Approving one
 * (OffboardingService::decideNoticeOverride()) is what actually mutates
 * offboarding_requests.last_working_date.
 */
class OffboardingNoticeOverride extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'offboarding_request_id', 'type', 'reason',
        'previous_last_working_date', 'requested_last_working_date',
        'requested_by', 'requested_at', 'status', 'approved_by', 'approved_at', 'decision_notes',
    ];

    protected $casts = [
        'previous_last_working_date' => 'date',
        'requested_last_working_date' => 'date',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    const TYPE_WAIVER = 'waiver';
    const TYPE_EARLY_RELEASE = 'early_release';
    const TYPE_EXTENSION = 'extension';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function offboardingRequest()
    {
        return $this->belongsTo(OffboardingRequest::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
