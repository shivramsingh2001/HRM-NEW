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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
