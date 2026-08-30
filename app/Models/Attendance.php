<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\TenantTrait;

class Attendance extends Model
{
    use TenantTrait;

    protected $guarded = [];

    /**
     * date / clock_in / clock_out are deliberately left uncast: the columns are
     * varchar/date and several call sites (summary keyBy('date'), the mobile API)
     * depend on the raw string form. Parse them through
     * App\Services\Attendance\AttendanceCalculator, never ad-hoc.
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(AttendanceTrack::class, 'attendance_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function regularizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regularized_by');
    }
}
