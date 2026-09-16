<?php
// app/Models/LoanCategory.php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanCategory extends Model
{
    use TenantTrait, HasFactory;

    protected $table = 'loan_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'max_amount',
        'default_interest_rate',
        'max_tenure_months',
        'requires_approval',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'default_interest_rate' => 'decimal:2',
        'max_tenure_months' => 'integer',
        'requires_approval' => 'boolean',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function loans()
    {
        return $this->hasMany(Loan::class, 'loan_type_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

  
    // Helper Methods
    public function isActive()
    {
        return $this->status == true;
    }

    public function calculateEmi($amount, $tenureMonths = null)
    {
        $tenure = $tenureMonths ?? $this->max_tenure_months;
        $rate = $this->default_interest_rate;
        
        if ($rate == 0) {
            return round($amount / $tenure, 2);
        }
        
        $monthlyRate = $rate / 100 / 12;
        $emi = ($amount * $monthlyRate * pow(1 + $monthlyRate, $tenure)) / (pow(1 + $monthlyRate, $tenure) - 1);
        
        return round($emi, 2);
    }

    public function validateAmount($amount)
    {
        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }
        
        return true;
    }

    public function validateTenure($tenure)
    {
        if ($tenure > $this->max_tenure_months) {
            return false;
        }
        
        return true;
    }
}