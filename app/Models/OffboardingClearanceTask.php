<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-request instantiated clearance checklist item. `source=asset` rows are
 * backed by a real AssetAssignment row — completing one calls through to
 * App\Services\Asset\AssetLifecycleService::returnAsset() so the real asset
 * record updates too, not just this task's status flag.
 */
class OffboardingClearanceTask extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'offboarding_request_id', 'source', 'clearance_template_id',
        'asset_assignment_id', 'category', 'label', 'status',
        'completed_by', 'completed_at', 'remarks',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    const SOURCE_CHECKLIST = 'checklist';
    const SOURCE_ASSET = 'asset';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_WAIVED = 'waived';
    const STATUS_NOT_APPLICABLE = 'not_applicable';

    public function offboardingRequest()
    {
        return $this->belongsTo(OffboardingRequest::class);
    }

    public function template()
    {
        return $this->belongsTo(OffboardingClearanceTemplate::class, 'clearance_template_id');
    }

    public function assetAssignment()
    {
        return $this->belongsTo(AssetAssignment::class, 'asset_assignment_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_WAIVED, self::STATUS_NOT_APPLICABLE], true);
    }
}
