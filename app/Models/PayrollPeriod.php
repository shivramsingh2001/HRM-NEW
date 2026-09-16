<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors AttendancePeriodLock — gives payroll periods real lock/close
 * semantics instead of a bare 'YYYY-MM' string.
 */
class PayrollPeriod extends Model
{
    use TenantTrait, LogsPayrollActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'pay_date' => 'date',
        'locked_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function runs()
    {
        return $this->hasMany(PayrollRun::class, 'payroll_period_id');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function scopeForMonth($query, string $yearMonth)
    {
        return $query->where('year_month', $yearMonth);
    }
}
