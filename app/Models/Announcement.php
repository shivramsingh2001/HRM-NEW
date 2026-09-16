<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Announcement extends Model
{
     use TenantTrait;
    protected $guarded = [];

    protected $casts = [
        'expire_date' => 'date',
        'status' => 'integer',
        'acknowledge' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function acknowledgments()
    {
        return $this->hasMany(AnnouncementAcknowledgment::class);
    }

    /**
     * Exclude announcements whose expire_date has passed.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expire_date')
                ->orWhere('expire_date', '>=', now()->toDateString());
        });
    }
}
