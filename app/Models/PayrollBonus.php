<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * One-off or recurring bonus, approval-gated. Kept distinct from a
 * recurring allowance component since it's inherently one-off/ad hoc.
 */
class PayrollBonus extends Model
{
    use TenantTrait, LogsPayrollActivity;

    protected $table = 'payroll_bonuses';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_recurring' => 'boolean',
        'recurrence_month' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class, 'target_payroll_period_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
