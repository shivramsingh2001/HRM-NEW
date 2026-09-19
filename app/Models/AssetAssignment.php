<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'asset_id', 'user_id', 'assigned_by', 'assigned_at', 'status',
        'expected_return_date', 'accepted_at', 'accepted_by',
        'returned_at', 'returned_to', 'return_condition',
        'remarks', 'acknowledgement_note',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'expected_return_date' => 'date',
        'accepted_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function returnedTo()
    {
        return $this->belongsTo(User::class, 'returned_to');
    }
}
