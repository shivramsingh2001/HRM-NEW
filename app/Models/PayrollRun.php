<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per "process payroll" batch execution for a period. engine_version
 * is the load-bearing field for the legacy/dynamic cutover strategy.
 */
class PayrollRun extends Model
{
    use TenantTrait, LogsPayrollActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'employees_included' => 'integer',
        'total_gross' => 'decimal:2',
        'total_net' => 'decimal:2',
        'total_deductions' => 'decimal:2',
    ];

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function monthlyPayrolls()
    {
        return $this->hasMany(MonthlyPayroll::class, 'payroll_run_id');
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function isDynamic(): bool
    {
        return $this->engine_version === 'dynamic_v1';
    }
}
