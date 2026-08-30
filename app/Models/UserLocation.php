<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class UserLocation extends Model
{
     use TenantTrait;
     protected $guarded = [];
    
    /**
     * Get the city details
     */
    public function cityDetail()
    {
        return $this->belongsTo(City::class, 'city', 'city_code');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function countryRel()
    {
        return $this->belongsTo(Country::class, 'country', 'country_code');
    }

    public function stateRel()
    {
        return $this->belongsTo(State::class, 'state', 'state_code');
    }

    public function cityRel()
    {
        return $this->belongsTo(City::class, 'city', 'city_code');
    }
}
