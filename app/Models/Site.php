<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A company site employees visit. `site_code` is system-generated from
 * `site_no` (see App\Services\Site\SiteService) and never edited.
 */
class Site extends Model
{
    use TenantTrait;
    use SoftDeletes;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'tenant_id', 'site_no', 'site_code', 'site_name', 'state_code', 'district',
        'address', 'location', 'latitude', 'longitude', 'onboarding_date', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'onboarding_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_code', 'state_code');
    }

    /** @return HasMany<SiteVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(SiteVisit::class, 'site_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
