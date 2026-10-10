<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/** Company Policies → Shift Requests (one row per company). */
class ShiftRequestSetting extends Model
{
    use TenantTrait;

    public const DEFAULTS = [
        'swap_enabled' => true,
        'change_enabled' => true,
        'requires_approval' => true,
        'min_notice_hours' => 24,
        'min_rest_hours' => 8,
        'max_requests_per_month' => 4,
        'peer_response_hours' => 24,
        'same_department_only' => true,
        'same_branch_only' => false,
        'notify_on_roster_change' => true,
    ];

    protected $guarded = [];

    protected $casts = [
        'swap_enabled' => 'boolean',
        'change_enabled' => 'boolean',
        'requires_approval' => 'boolean',
        'same_department_only' => 'boolean',
        'same_branch_only' => 'boolean',
        'notify_on_roster_change' => 'boolean',
        'min_notice_hours' => 'integer',
        'min_rest_hours' => 'integer',
        'max_requests_per_month' => 'integer',
        'peer_response_hours' => 'integer',
    ];

    /** The company's row, or an unsaved row holding the defaults. */
    public static function forTenant(int $tenantId): self
    {
        return static::withoutGlobalScopes()->where('tenant_id', $tenantId)->first()
            ?? new static(['tenant_id' => $tenantId] + self::DEFAULTS);
    }
}
