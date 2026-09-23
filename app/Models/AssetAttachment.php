<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetAttachment extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'asset_id', 'file_path', 'uploaded_by',
        'context', 'original_filename', 'mime_type', 'file_size',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
