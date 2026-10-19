<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One saved week-off swap (worked day → replacement day off) — see WeekOffSwapService. */
class WeekoffSwap extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'worked_date' => 'date',
        'replacement_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(WeekoffSwapEmployee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
