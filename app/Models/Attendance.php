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

    protected static function booted(): void
    {
        // Tier 1 / W4 — keep clock_in_utc / clock_out_utc / tz in step with the
        // legacy local-time strings for EVERY writer (funnel, mobile clock-in/out,
        // fingerprint, auto-clock-out), so a later release can derive the strings
        // from the UTC columns. Skipped when a caller set the UTC value itself.
        static::saving(function (Attendance $a) {
            $touchesClock = $a->isDirty('clock_in') || $a->isDirty('clock_out');
            if (! $touchesClock || ! $a->user_id) {
                return;
            }

            $calc = new \App\Services\Attendance\AttendanceCalculator();
            $tz = $a->tz ?: app(\App\Services\Attendance\TimezoneResolver::class)
                ->forUser((int) $a->user_id, $a->tenant_id ? (int) $a->tenant_id : null);
            $a->tz = $tz;

            if ($a->isDirty('clock_in') && ! $a->isDirty('clock_in_utc')) {
                $a->clock_in_utc = $calc->toUtc($a->clock_in, $tz)?->format('Y-m-d H:i:s');
            }
            if ($a->isDirty('clock_out') && ! $a->isDirty('clock_out_utc')) {
                $a->clock_out_utc = $calc->toUtc($a->clock_out, $tz)?->format('Y-m-d H:i:s');
            }
        });
    }

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
