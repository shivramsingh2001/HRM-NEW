<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class OvertimeRequest extends Model
{
    use TenantTrait ;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'created_by', // admin / HR who raised it for the employee (NULL = the employee)
        'date',
        'overtime_hours',
        'reason',
        'status',
        'approved_by',
        'approved_hours',
        'rejection_reason',
        'approved_at',
        'source', // request | auto (calculated from attendance, Company Policies → Overtime)
        'attendance_id',
        'auto_minutes',
        'manually_adjusted_at',
    ];

    public const SOURCE_REQUEST = 'request';
    public const SOURCE_AUTO = 'auto';

    protected $casts = [
        // 'date' => 'date',
        // 'approved_at' => 'datetime',
        'overtime_hours' => 'decimal:2',
        'approved_hours' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    // Accessors
    public function getFinalOvertimeHoursAttribute()
    {
        return $this->approved_hours ?? $this->overtime_hours;
    }
}