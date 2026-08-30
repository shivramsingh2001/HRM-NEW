<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\TenantTrait;

class RequestType extends Model
{
    use HasFactory;

    protected $table = 'request_types';

    protected $fillable = [
        'type_name', 'description', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function requests()
    {
        return $this->hasMany(Request::class, 'request_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWfh($query)
    {
        return $query->where('type_name', 'WFH');
    }

    public function scopeTravel($query)
    {
        return $query->where('type_name', 'TRAVEL');
    }
}