<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Professional Tax slab — state-specific and genuinely row-per-band, hence
 * tabular rather than JSON. Resolution uses tenants.state as the default
 * work state.
 */
class StatutoryPtSlab extends Model
{
    use TenantTrait, LogsPayrollActivity;

    protected $table = 'statutory_pt_slabs';

    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'gross_salary_min' => 'decimal:2',
        'gross_salary_max' => 'decimal:2',
        'pt_amount' => 'decimal:2',
    ];

    public function scopeForSalary($query, string $stateCode, $grossSalary, $date, string $gender = 'all')
    {
        return $query->where('state_code', $stateCode)
            ->whereIn('gender', ['all', $gender])
            ->where('gross_salary_min', '<=', $grossSalary)
            ->where(function ($q) use ($grossSalary) {
                $q->whereNull('gross_salary_max')->orWhere('gross_salary_max', '>=', $grossSalary);
            })
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }
}
