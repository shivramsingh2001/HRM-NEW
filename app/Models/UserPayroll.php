<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;
use App\Traits\LogsPayrollActivity;
class UserPayroll extends Model
{
    use TenantTrait, HasFactory, SoftDeletes, LogsPayrollActivity;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'payroll_master_id',
        'payroll_code',
        'effective_from',
        'effective_to',
        'is_current',
        'basic_salary',
        'hra',
        'conveyence',
        'medical_allowance',
        'children_allowance',
        'post_allowance',
        'leave_travel_allowance',
        'monthly_incentive',
        'special_allowance',
        'provident_fund',
        'employer_provident_fund',
        'esi',
        'employer_esi',
        'professional_tax',
        'tds',
        'gross_salary',
        'total_deductions',
        'net_salary',
        'ctc',
        'notes',
        'status'
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_current' => 'boolean',
        'status' => 'boolean',
        'basic_salary' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'ctc' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payrollMaster()
    {
        return $this->belongsTo(PayrollMaster::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function monthlyPayrolls()
    {
        // Fix: Change 'user_payroll_id' to 'employee_payroll_id'
        return $this->hasMany(MonthlyPayroll::class, 'employee_payroll_id', 'id');
    }

    // Scopes
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true)->where('status', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeEffective($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            });
    }
}
