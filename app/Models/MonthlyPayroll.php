<?php
// app/Models/MonthlyPayroll.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
use App\Traits\LogsPayrollActivity;

class MonthlyPayroll extends Model
{
    use TenantTrait, HasFactory, LogsPayrollActivity;

    protected $table = 'monthly_payrolls';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_payroll_id',
        'payroll_run_id',
        'payroll_month',
        'processing_date',
        'total_working_days',
        'present_days',
        'absent_days',
        'paid_leaves',
        'unpaid_leaves',
        'holidays',
        'week_offs',
        'overtime_hours',
        'basic_salary',
        'hra',
        'conveyence',
        'medical_allowance',
        'children_allowance',
        'post_allowance',
        'leave_travel_allowance',
        'monthly_incentive',
        'special_allowance',
        'overtime_amount',
        'provident_fund',
        'employer_provident_fund',
        'esi',
        'employer_esi',
        'professional_tax',
        'tds',
        'loan_deduction',
        'loan_deduction_enabled',
        'loan_deduction_computed',
        'other_deductions',
        'gross_earnings',
        'total_deductions',
        'net_payable',
        'payment_status',
        'payment_date',
        'payment_mode',
        'transaction_reference',
        'remarks',
        'processed_by',
        'payable_days',
        'expected_hours',
        'actual_worked_hours',
        'hourly_rate',
        'overtime_rate',
        'overtime_hours_calculated',
        'half_days',
        'engine_version',
    ];

    protected $casts = [
        'processing_date' => 'date',
        'payment_date' => 'date',
        'total_working_days' => 'integer',
        'present_days' => 'decimal:2',
        'absent_days' => 'decimal:2',
        'half_days' => 'decimal:2',
        'paid_leaves' => 'decimal:2',
        'unpaid_leaves' => 'decimal:2',
        'holidays' => 'integer',
        'week_offs' => 'integer',
        'overtime_hours' => 'decimal:2',
        'expected_hours' => 'decimal:2',
        'actual_worked_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'overtime_hours_calculated' => 'decimal:2',
        'payable_days' => 'decimal:2',
        'basic_salary' => 'decimal:2',
        'hra' => 'decimal:2',
        'conveyence' => 'decimal:2',
        'medical_allowance' => 'decimal:2',
        'children_allowance' => 'decimal:2',
        'post_allowance' => 'decimal:2',
        'leave_travel_allowance' => 'decimal:2',
        'monthly_incentive' => 'decimal:2',
        'special_allowance' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'provident_fund' => 'decimal:2',
        'employer_provident_fund' => 'decimal:2',
        'esi' => 'decimal:2',
        'employer_esi' => 'decimal:2',
        'professional_tax' => 'decimal:2',
        'tds' => 'decimal:2',
        'loan_deduction' => 'decimal:2',
        'loan_deduction_enabled' => 'boolean',
        'loan_deduction_computed' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'gross_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_payable' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function UserPayroll()
    {
        return $this->belongsTo(UserPayroll::class,'employee_payroll_id');
    }
    public function payrollMaster()
    {
        return $this->belongsTo(PayrollMaster::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function components()
    {
        return $this->hasMany(PayrollComponent::class, 'monthly_payroll_id');
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    // Scopes
    public function scopeForMonth($query, $yearMonth)
    {
        return $query->where('payroll_month', $yearMonth);
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('payment_status', 'processed');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    // Accessors
    public function getFormattedNetPayableAttribute()
    {
        return '₹ ' . number_format($this->net_payable, 2);
    }

    public function getMonthNameAttribute()
    {
        return date('F Y', strtotime($this->payroll_month . '-01'));
    }
}