<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * A queued retroactive-pay delta (from a backdated revision, correction, or
 * backdated bonus) waiting to be folded into the next payroll run as a
 * system "Arrears" component line.
 */
class PayrollArrears extends Model
{
    use TenantTrait, LogsPayrollActivity;

    protected $table = 'payroll_arrears';

    protected $guarded = ['id'];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'revised_amount' => 'decimal:2',
        'arrears_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function revisionLog()
    {
        return $this->belongsTo(PayrollRevisionLog::class, 'payroll_revision_log_id');
    }

    public function component()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'component_id');
    }

    public function targetPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class, 'target_payroll_period_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
