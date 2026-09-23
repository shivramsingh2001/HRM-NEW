<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetTransfer extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'asset_id', 'from_user_id', 'to_user_id',
        'from_branch_id', 'to_branch_id', 'transferred_by', 'transferred_at', 'reason',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function fromBranch()
    {
        return $this->belongsTo(CompanyBranch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(CompanyBranch::class, 'to_branch_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
