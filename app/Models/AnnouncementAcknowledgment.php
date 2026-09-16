<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class AnnouncementAcknowledgment extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
