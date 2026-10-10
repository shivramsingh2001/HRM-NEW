<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * An employee shift swap / shift change request, or an admin/manager direct
 * swap or change (mode = direct, saved already approved). See
 * App\Services\Shift\ShiftRequestService.
 */
class ShiftRequest extends Model
{
    use TenantTrait;

    public const STATUS_PENDING_PEER = 'pending_peer';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PEER_DECLINED = 'peer_declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVERTED = 'reverted';

    public const PENDING = [self::STATUS_PENDING_PEER, self::STATUS_PENDING_APPROVAL];

    public const STATUS_LABELS = [
        self::STATUS_PENDING_PEER => 'Waiting for colleague',
        self::STATUS_PENDING_APPROVAL => 'Waiting for approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_PEER_DECLINED => 'Declined by colleague',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_REVERTED => 'Reverted',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDING_PEER => 'bg-soft-warning text-warning',
        self::STATUS_PENDING_APPROVAL => 'bg-soft-warning text-warning',
        self::STATUS_APPROVED => 'bg-soft-success text-success',
        self::STATUS_REJECTED => 'bg-soft-danger text-danger',
        self::STATUS_PEER_DECLINED => 'bg-soft-danger text-danger',
        self::STATUS_CANCELLED => 'bg-soft-secondary text-secondary',
        self::STATUS_EXPIRED => 'bg-soft-secondary text-secondary',
        self::STATUS_REVERTED => 'bg-soft-info text-info',
    ];

    protected $guarded = [];

    protected $casts = [
        'peer_responded_at' => 'datetime',
        'decided_at' => 'datetime',
        'reverted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ShiftRequestItem::class, 'shift_request_id')->orderBy('date')->orderBy('id');
    }

    public function events()
    {
        return $this->hasMany(ShiftRequestEvent::class, 'shift_request_id')->orderBy('id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function counterpart()
    {
        return $this->belongsTo(User::class, 'counterpart_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::PENDING, true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-soft-secondary text-secondary';
    }

    public function typeLabel(): string
    {
        return $this->type === 'swap' ? 'Shift swap' : 'Shift change';
    }

    /**
     * The employee the request is about — ApprovalService resolves the
     * reporting head / workflow match from `$subject->user_id`.
     */
    public function getUserIdAttribute(): int
    {
        return (int) $this->requester_id;
    }

    /** Everyone whose shift this request changes. */
    public function involvedUserIds(): array
    {
        return array_values(array_unique(array_filter([(int) $this->requester_id, (int) $this->counterpart_id])));
    }
}
