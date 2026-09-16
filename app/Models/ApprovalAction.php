<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalAction extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'acted_at' => 'datetime',
        'meta' => 'array',
    ];
}
