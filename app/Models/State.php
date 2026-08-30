<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'country_code');
    }

    /**
     * Get all cities for the state
     */
    public function cities()
    {
        return $this->hasMany(City::class, 'state_code', 'state_code');
    }
}
