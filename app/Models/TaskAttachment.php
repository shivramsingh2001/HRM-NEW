<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class TaskAttachment extends Model
{
    use TenantTrait;

    protected $guarded = [];
    public $timestamps = false;
    protected $dates = ['created_at'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function comment()
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute()
    {
        return file_url($this->file_path, 'task_attachment');
    }

    public function getFormattedSizeAttribute()
    {
        if (!$this->file_size) {
            return null;
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1) . ' ' . $units[$i];
    }
}
