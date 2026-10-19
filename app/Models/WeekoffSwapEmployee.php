<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One employee of a week-off swap: their replacement day off and the user_weekoffs row it created. */
class WeekoffSwapEmployee extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'worked_date' => 'date',
        'replacement_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function swap(): BelongsTo
    {
        return $this->belongsTo(WeekoffSwap::class, 'weekoff_swap_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
