<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class LeaveType extends Model
{
     use TenantTrait;
    protected $guarded = [];

    /**
     * True for a system-managed type (e.g. LWP) that must not be renamed,
     * reconfigured, or deleted via the admin UI.
     */
    public function isSystemType(): bool
    {
        return !is_null($this->code);
    }

    /** The period unused days carry into: month / week / leave_year (null when nothing is credited). */
    public function carryForwardPeriod(): ?string
    {
        return match ($this->credit_type) {
            'monthly' => 'month',
            'weekly' => 'week',
            'yearly' => 'leave_year',
            default => null,
        };
    }

    /** e.g. "1.5 days carry to next month", "Nothing carries to next week", "No limit". */
    public function carryForwardText(): string
    {
        $next = match ($this->carryForwardPeriod()) {
            'month' => 'next month',
            'week' => 'next week',
            default => 'next leave year',
        };
        if ($this->max_carry_forward === null) {
            return 'No limit — all unused days carry to ' . $next;
        }
        $days = (float) $this->max_carry_forward;
        if ($days <= 0) {
            return 'Nothing carries to ' . $next;
        }
        $n = rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');

        return $n . ' day' . ($n === '1' ? '' : 's') . ' carry to ' . $next;
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class, 'leave_type');
    }
}
