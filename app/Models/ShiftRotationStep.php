<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One day of a rotation pattern's cycle; shift_id null = day off. */
class ShiftRotationStep extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
