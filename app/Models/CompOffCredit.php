<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One worked week-off / holiday claimed as comp-off — see App\Services\Leave\CompOffService. */
class CompOffCredit extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'worked_date' => 'date',
        'expires_on' => 'date',
        'decided_at' => 'datetime',
        'worked_hours' => 'float',
        'days' => 'float',
        'lapsed_days' => 'float',
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

    /** One row of the mobile API (GET /api/comp-off, /api/manager/comp-off). */
    public function toApi(bool $withEmployee = false): array
    {
        return [
            'id' => $this->id,
            'worked_date' => $this->worked_date->toDateString(),
            'day_type' => $this->day_type,
            'worked_hours' => $this->worked_hours,
            'days' => $this->days,
            'status' => $this->status,
            'source' => $this->source,
            'reason' => $this->reason,
            'decision_remarks' => $this->decision_remarks,
            'decided_by' => $this->decider?->name,
            'decided_at' => $this->decided_at?->format('Y-m-d H:i:s'),
            'expires_on' => $this->expires_on?->toDateString(),
            'lapsed_days' => $this->lapsed_days,
            'can_cancel' => $this->status === 'pending',
        ] + ($withEmployee ? ['employee' => ['id' => $this->user_id, 'name' => $this->user?->name, 'employee_id' => $this->user?->employee_id]] : []);
    }
}
