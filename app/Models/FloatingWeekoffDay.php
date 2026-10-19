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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
