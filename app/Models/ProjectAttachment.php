<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class ProjectAttachment extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'user_id',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
