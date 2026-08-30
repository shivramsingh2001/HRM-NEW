<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantTrait;

class RequestHistory extends Model
{
    use HasFactory, TenantTrait;


    protected $fillable = [
        'request_id',
        'action_by',
        'action',
        'comments',
        'old_values',
        'new_values'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    const ACTION_CREATED = 'CREATED';
    const ACTION_SUBMITTED = 'SUBMITTED';
    const ACTION_APPROVED = 'APPROVED';
    const ACTION_REJECTED = 'REJECTED';
    const ACTION_CANCELLED = 'CANCELLED';
    const ACTION_UPDATED = 'UPDATED';

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    // Accessors
    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            self::ACTION_APPROVED => 'success',
            self::ACTION_REJECTED, self::ACTION_CANCELLED => 'danger',
            self::ACTION_CREATED => 'info',
            default => 'warning',
        };
    }
}