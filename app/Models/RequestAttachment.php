<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantTrait;

class RequestAttachment extends Model
{
    use HasFactory, TenantTrait;

    protected $table = 'request_attachments';

    protected $fillable = [
        'request_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by'
    ];

    protected $appends = ['file_url'];

    // Relationships
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Accessors
    public function getFileUrlAttribute()
    {
        return file_url($this->file_path, 'request');
    }

    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
