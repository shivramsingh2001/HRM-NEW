<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One employee's carry forward for one leave type into one leave year —
 * written by `leaves:carry-forward` (App\Console\Commands\CarryForwardLeaves).
 */
class LeaveCarryForward extends Model
{
    protected $guarded = [];

    protected $casts = [
        'leave_year_start' => 'date',
        'expires_on' => 'date',
        'expired_at' => 'datetime',
        'closing_balance' => 'float',
        'carry_limit' => 'float',
        'carried' => 'float',
        'lapsed' => 'float',
        'expired' => 'float',
    ];
}
