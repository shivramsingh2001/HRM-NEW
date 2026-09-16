<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One row per employee per salary version — replaces the *role* of
 * UserPayroll for tenants on the new dynamic engine, without touching
 * UserPayroll itself.
 */
class PayrollEmployeeStructure extends Model
{
    use TenantTrait, HasFactory, SoftDeletes, LogsPayrollActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_current' => 'boolean',
        'ctc' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function structure()
    {
        return $this->belongsTo(PayrollStructure::class, 'payroll_structure_id');
    }

    public function components()
    {
        return $this->hasMany(PayrollEmployeeComponent::class, 'payroll_employee_structure_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * The structure that was actually effective on a given date — the
     * dynamic-engine equivalent of UserPayroll::scopeEffective(), used by
     * the calculation engine so historical/backfilled months use the
     * salary that was in effect then, not whatever is_current today.
     */
    public function scopeEffective($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Enforces "only one is_current=1 per user" transactionally — replaces
     * the old system's bypassable, ad hoc per-controller checks. Any new
     * "current" row atomically supersedes whichever row was current before.
     *
     * Hooks both creating (the no-approval-workflow path, where a revision
     * goes live immediately) and updating (the Phase 5 approval-workflow
     * path, where a revision is created with is_current=false/
     * status=pending_approval and only flips to current once
     * PayrollRevisionApprovalHandler::approved() activates it).
     */
    protected static function booted()
    {
        static::creating(function (self $structure) {
            if ($structure->is_current) {
                self::supersedeOthers($structure);
            }
        });

        static::updating(function (self $structure) {
            if ($structure->is_current && $structure->isDirty('is_current')) {
                self::supersedeOthers($structure);
            }
        });
    }

    /**
     * Closes out every other current row for this user — but only ones that
     * genuinely started before this one. A backdated revision (this row's
     * effective_from earlier than a prior row's own effective_from) must
     * never close that later row with an effective_to before its
     * effective_from — that produces a permanently unreachable row (no date
     * can satisfy scopeEffective()'s BETWEEN check) rather than reopening
     * or resolving the actual overlap. Such rows are left untouched and
     * logged instead of guessed at.
     */
    private static function supersedeOthers(self $structure): void
    {
        DB::transaction(function () use ($structure) {
            $newEffectiveFrom = \Carbon\Carbon::parse($structure->effective_from);

            $candidates = self::withoutGlobalScope('tenant')
                ->where('tenant_id', $structure->tenant_id)
                ->where('user_id', $structure->user_id)
                ->where('is_current', true)
                ->when($structure->exists, fn ($q) => $q->where('id', '!=', $structure->id))
                ->lockForUpdate()
                ->get();

            foreach ($candidates as $prior) {
                if (\Carbon\Carbon::parse($prior->effective_from)->greaterThanOrEqualTo($newEffectiveFrom)) {
                    Log::channel('daily')->warning('Skipped superseding a payroll structure that starts on/after the incoming revision — would have produced an inverted effective_to range.', [
                        'prior_structure_id' => $prior->id,
                        'prior_effective_from' => $prior->effective_from,
                        'incoming_effective_from' => $structure->effective_from,
                        'user_id' => $structure->user_id,
                        'tenant_id' => $structure->tenant_id,
                    ]);

                    continue;
                }

                $prior->update([
                    'is_current' => false,
                    'status' => 'superseded',
                    'effective_to' => $newEffectiveFrom->copy()->subDay()->toDateString(),
                ]);
            }
        });
    }
}
