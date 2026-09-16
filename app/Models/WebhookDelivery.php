<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];
}
