<?php
// app/Models/PayrollComponent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;


class PayrollComponent extends Model
{
    
    use TenantTrait, HasFactory;

    protected $fillable = [
        'monthly_payroll_id',
        'component_name',
        'component_type',
        'amount',
        'is_taxable',
        'description'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_taxable' => 'boolean'
    ];

    // Relationships
    public function monthlyPayroll()
    {
        return $this->belongsTo(MonthlyPayroll::class);
    }

    // Scopes
    public function scopeEarnings($query)
    {
        return $query->where('component_type', 'earning');
    }

    public function scopeDeductions($query)
    {
        return $query->where('component_type', 'deduction');
    }
}