<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One floating week-off day: filled from a missed day by the nightly allocator
 * (source auto) or planned ahead by the employee (source planned, approved by a
 * manager / HR) — see FloatingWeekOffAllocator / FloatingWeekOffRequestService.
 */
class FloatingWeekoffDay extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** One row of the mobile API (GET /api/floating-weekoffs, /api/manager/floating-weekoffs). */
    public function toApi(bool $withEmployee = false): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'source' => $this->source,
            'status' => $this->status,
            'reason' => $this->reason,
            'decision_remarks' => $this->decision_remarks,
            'decided_by' => $this->decider?->name,
            'decided_at' => $this->decided_at?->format('Y-m-d H:i:s'),
            'can_cancel' => $this->source === 'planned'
                && ($this->status === 'pending' || ($this->status === 'approved' && $this->date->toDateString() >= now()->toDateString())),
        ] + ($withEmployee ? ['employee' => ['id' => $this->user_id, 'name' => $this->user?->name, 'employee_id' => $this->user?->employee_id]] : []);
    }
}
