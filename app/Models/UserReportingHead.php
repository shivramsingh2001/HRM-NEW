<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class UserReportingHead extends Model
{
    use TenantTrait;

    protected $table = 'user_reporting_heads';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'reporting_head_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reportingHead()
    {
        return $this->belongsTo(User::class, 'reporting_head_id');
    }
}
