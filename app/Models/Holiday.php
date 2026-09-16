<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
use Carbon\Carbon;

class Holiday extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'start_date',
        'end_date',
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function getIsMultiDayAttribute(): bool
    {
        return ! $this->start_date->isSameDay($this->end_date);
    }

    public function getDurationDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * start_date/end_date are plain VARCHAR columns (not native DATE
     * columns), and other code (report queries) compares them as raw
     * 'Y-m-d' strings. A plain 'date' cast would serialize for storage
     * using the connection's datetime format ('Y-m-d H:i:s'), silently
     * appending a " 00:00:00" suffix that breaks those string comparisons
     * — and overriding the model-wide $dateFormat to avoid that would also
     * truncate the real created_at/updated_at timestamp columns. These
     * explicit accessors/mutators give a Carbon instance on read (for
     * Blade ->format() calls and the accessors above) while always storing
     * a clean 'Y-m-d' string, without touching anything else.
     */
    protected function startDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    protected function endDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }
}
