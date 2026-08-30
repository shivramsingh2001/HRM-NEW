<?php
// app/Models/ExpenseStatusHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
class ExpenseStatusHistory extends Model
{
     use TenantTrait;
    protected $fillable = [
        'tenant_id',
        'expense_id',
        'status',
        'changed_by',
        'remarks'
    ];

    protected $casts = [
        'created_at' => 'datetime'
    ];

    /**
     * Get the expense this history belongs to
     */
    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Get the user who changed the status
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeAttribute()
    {
        $colors = [
            'pending' => 'warning',
            'approved' => 'info',
            'cancelled' => 'danger',
            'complete' => 'success'
        ];
        
        return $colors[$this->status] ?? 'secondary';
    }
}