<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class UserWeekoffs extends Model
{
    use TenantTrait;
    protected $guarded = [];

    /**
     * Leave out the days off the system manages itself: an active week-off swap's
     * day (cancelled from Shift → Week-off Swaps) and a floating week-off (moved by
     * the nightly weekoffs:allocate-floating).
     */
    public function scopeExceptSwapDays($query)
    {
        return $query->whereNotIn('id', fn ($q) => $q->from('weekoff_swap_employees')
            ->where('status', 'active')->whereNotNull('user_weekoff_id')->select('user_weekoff_id'))
            ->whereNotIn('id', fn ($q) => $q->from('floating_weekoff_days')->whereNotNull('user_weekoff_id')->select('user_weekoff_id'));
    }
}
