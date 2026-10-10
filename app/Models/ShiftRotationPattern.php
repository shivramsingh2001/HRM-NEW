<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/** A repeating cycle of shifts, e.g. 7×Morning, 7×Evening, 7×Night, or 4 on / 3 off. */
class ShiftRotationPattern extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'cycle_days' => 'integer',
    ];

    public function steps()
    {
        return $this->hasMany(ShiftRotationStep::class, 'pattern_id')->orderBy('day_index');
    }

    /** shift_id (null = day off) for each day of the cycle, indexed 0..cycle_days-1. */
    public function stepMap(): array
    {
        $map = array_fill(0, max(1, (int) $this->cycle_days), null);
        foreach ($this->steps as $step) {
            if ($step->day_index < $this->cycle_days) {
                $map[$step->day_index] = $step->shift_id ? (int) $step->shift_id : null;
            }
        }

        return $map;
    }

    /** Cycle day for $date when day 0 falls on $anchor (works before the anchor too). */
    public static function cycleIndex(\Carbon\Carbon $anchor, \Carbon\Carbon $date, int $cycleDays): int
    {
        $diff = (int) $anchor->copy()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        return (($diff % $cycleDays) + $cycleDays) % $cycleDays;
    }
}
