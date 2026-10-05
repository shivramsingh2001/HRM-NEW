<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leave carry forward (Company Policies → Leave carry forward + the leave
 * type's "Carry forward" fields). Run daily by `leaves:carry-forward`.
 *
 * 1. Period end — once per credit period of the type (monthly: the 1st of each
 *    month, weekly: each Monday, yearly: the company's leave-year start), for
 *    every (employee, leave type) whose type has a carry-forward limit:
 *    closing balance of the previous period = current balance minus everything
 *    booked since the period started (so a credit that already ran this
 *    morning is never lapsed). Days above the limit lapse (a `sub` leave
 *    transaction); the outcome is stored in leave_carry_forwards, whose
 *    leave_year_start column holds the period start.
 * 2. Expiry (yearly types only) — when a carried row's expires_on is reached,
 *    carried days not yet used lapse. Leave taken in the new year is counted
 *    against carried days first.
 *
 * Nothing happens while the company switch is off, and a period that started
 * before the switch was turned on is never carried forward retroactively.
 * Unpaid types and types with credit type "no" are skipped.
 */
class LeaveCarryForwardService
{
    public function __construct(private LeaveYearService $leaveYear)
    {
    }

    /**
     * @return array{skipped:?string, year_start:string, processed:int, lapsed_rows:int, lapsed_days:float,
     *               expired_rows:int, expired_days:float, lines:array<int,string>}
     */
    public function run(int $tenantId, Carbon $today, bool $dryRun = false): array
    {
        $today = $today->copy()->startOfDay();
        $yearStart = $this->leaveYear->startFor($tenantId, $today);
        $stats = ['skipped' => null, 'year_start' => $yearStart->toDateString(), 'processed' => 0, 'lapsed_rows' => 0,
            'lapsed_days' => 0.0, 'expired_rows' => 0, 'expired_days' => 0.0, 'lines' => []];

        $tenant = DB::table('tenants')->where('id', $tenantId)
            ->first(['leave_carry_forward_enabled', 'leave_carry_forward_enabled_at']);
        if (! $tenant || ! $tenant->leave_carry_forward_enabled) {
            $stats['skipped'] = 'carry forward is switched off';

            return $stats;
        }

        DB::beginTransaction();
        try {
            $enabledOn = $tenant->leave_carry_forward_enabled_at ? Carbon::parse($tenant->leave_carry_forward_enabled_at)->startOfDay() : null;
            $periods = [
                'yearly' => $yearStart,
                'monthly' => $today->copy()->startOfMonth(),
                'weekly' => $today->copy()->startOfWeek(Carbon::MONDAY),
            ];
            foreach ($periods as $creditType => $periodStart) {
                if ($enabledOn && $periodStart->lt($enabledOn)) {
                    $stats['lines'][] = ucfirst($creditType) . " types: the period from {$periodStart->toDateString()} started before carry forward was switched on ({$enabledOn->toDateString()}) — first run at the next period.";
                    continue;
                }
                $this->periodEnd($tenantId, $creditType, $periodStart, $today, $stats);
            }
            $this->expire($tenantId, $today, $dryRun, $stats);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    /** The period that just closed, for remarks: "Nov 2026", "week of 23 Nov 2026", "leave year 2026-27". */
    private function periodLabel(string $creditType, Carbon $periodStart): string
    {
        return match ($creditType) {
            'monthly' => $periodStart->copy()->subMonthNoOverflow()->format('M Y'),
            'weekly' => 'week of ' . $periodStart->copy()->subWeek()->format('d M Y'),
            default => 'leave year ' . $this->leaveYear->label($periodStart),
        };
    }

    private function periodEnd(int $tenantId, string $creditType, Carbon $yearStart, Carbon $today, array &$stats): void
    {
        $types = DB::table('leave_types')->where('tenant_id', $tenantId)
            ->where('is_unpaid', 0)->where('credit_type', $creditType)
            ->where(fn ($q) => $creditType === 'yearly'
                ? $q->whereNotNull('max_carry_forward')->orWhereNotNull('carry_forward_expiry_months')
                : $q->whereNotNull('max_carry_forward'))
            ->get(['id', 'name', 'max_carry_forward', 'carry_forward_expiry_months'])->keyBy('id');
        if ($types->isEmpty()) {
            return;
        }

        $done = DB::table('leave_carry_forwards')->where('tenant_id', $tenantId)
            ->where('leave_year_start', $yearStart->toDateString())
            ->get(['user_id', 'leave_type_id'])->map(fn ($r) => $r->user_id . '|' . $r->leave_type_id)->flip();

        $balances = DB::table('leave_balances as b')->join('users as u', 'u.id', '=', 'b.user_id')
            ->where('b.tenant_id', $tenantId)->where('u.status', 1)
            ->whereIn('b.leave_type_id', $types->keys())
            ->get(['b.id', 'b.user_id', 'b.leave_type_id', 'b.balance', 'u.name']);

        // Net booked since the year started (add − sub), per user|type — one query.
        $since = DB::table('leave_transactions')->where('tenant_id', $tenantId)
            ->whereIn('leave_type', $types->keys())
            ->where('created_at', '>=', $yearStart->toDateTimeString())
            ->groupBy('user_id', 'leave_type')
            ->select('user_id', 'leave_type', DB::raw("SUM(CASE WHEN transaction_type = 'add' THEN leaves_count ELSE -leaves_count END) as net"))
            ->get()->mapWithKeys(fn ($r) => [$r->user_id . '|' . $r->leave_type => (float) $r->net]);

        $label = $this->periodLabel($creditType, $yearStart);

        foreach ($balances as $b) {
            $key = $b->user_id . '|' . $b->leave_type_id;
            if (isset($done[$key])) {
                continue;
            }
            $type = $types[$b->leave_type_id];
            $current = (float) $b->balance;
            $closing = max(0.0, round($current - ($since[$key] ?? 0.0), 2));
            $limit = $type->max_carry_forward !== null ? (float) $type->max_carry_forward : null;
            $carried = $limit === null ? $closing : min($closing, $limit);
            // Never take the balance below zero (leave already used today, etc.).
            $lapsed = round(min($closing - $carried, max(0.0, $current)), 2);
            $expiresOn = $creditType === 'yearly' && $type->carry_forward_expiry_months && $carried > 0
                ? $yearStart->copy()->addMonthsNoOverflow((int) $type->carry_forward_expiry_months)->toDateString()
                : null;

            $stats['processed']++;
            if ($lapsed > 0) {
                $stats['lapsed_rows']++;
                $stats['lapsed_days'] += $lapsed;
                $stats['lines'][] = "{$b->name} · {$type->name}: closing {$closing}, carried {$carried}, lapsed {$lapsed}";
                $this->debit($tenantId, (int) $b->user_id, (int) $b->leave_type_id, $lapsed, $current, $today,
                    'Carry forward: ' . $this->days($lapsed) . ' lapsed (limit ' . $this->days((float) $limit) . ") — {$label}");
            }

            DB::table('leave_carry_forwards')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $b->user_id,
                'leave_type_id' => $b->leave_type_id,
                'leave_year_start' => $yearStart->toDateString(),
                'closing_balance' => $closing,
                'carry_limit' => $limit,
                'carried' => $carried,
                'lapsed' => $lapsed,
                'expires_on' => $expiresOn,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function expire(int $tenantId, Carbon $today, bool $dryRun, array &$stats): void
    {
        $rows = DB::table('leave_carry_forwards as c')
            ->join('leave_types as t', 't.id', '=', 'c.leave_type_id')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.tenant_id', $tenantId)
            ->where('t.credit_type', 'yearly')
            ->whereNotNull('c.expires_on')->where('c.expires_on', '<=', $today->toDateString())
            ->whereNull('c.expired_at')
            ->get(['c.*', 't.name as type_name', 'u.name as user_name']);

        foreach ($rows as $row) {
            // Leave taken since the year started (approvals minus revocations), carried days used first.
            $used = (float) DB::table('leave_transactions')->where('tenant_id', $tenantId)
                ->where('user_id', $row->user_id)->where('leave_type', $row->leave_type_id)
                ->whereNotNull('leave_id')
                ->where('created_at', '>=', Carbon::parse($row->leave_year_start)->toDateTimeString())
                ->sum(DB::raw("CASE WHEN transaction_type = 'sub' THEN leaves_count ELSE -leaves_count END"));

            $current = (float) DB::table('leave_balances')->where('tenant_id', $tenantId)
                ->where('user_id', $row->user_id)->where('leave_type_id', $row->leave_type_id)->value('balance');
            $expire = round(min(max(0.0, (float) $row->carried - max(0.0, $used)), max(0.0, $current)), 2);

            if ($expire > 0) {
                $stats['expired_rows']++;
                $stats['expired_days'] += $expire;
                $stats['lines'][] = "{$row->user_name} · {$row->type_name}: carried {$row->carried}, used {$used}, expired {$expire}";
                $this->debit($tenantId, (int) $row->user_id, (int) $row->leave_type_id, $expire, $current, $today,
                    'Carried leave expired: ' . $this->days($expire) . ' not used by ' . Carbon::parse($row->expires_on)->format('d M Y'));
            }

            DB::table('leave_carry_forwards')->where('id', $row->id)
                ->update(['expired' => $expire, 'expired_at' => now(), 'updated_at' => now()]);
        }
    }

    private function debit(int $tenantId, int $userId, int $typeId, float $days, float $before, Carbon $today, string $remarks): void
    {
        $after = round($before - $days, 2);
        DB::table('leave_balances')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('leave_type_id', $typeId)->update(['balance' => $after, 'updated_at' => now()]);

        DB::table('leave_transactions')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'leave_type' => $typeId,
            'transaction_type' => 'sub',
            'total_leaves' => $days,
            'leaves_count' => $days,
            'before_leaves' => $before,
            'after_leaves' => $after,
            'transaction_date' => $today->toDateString(),
            'leave_detail' => 'paid',
            'remarks' => $remarks,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function days(float $n): string
    {
        $n = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        return $n . ' day' . ($n === '1' ? '' : 's');
    }
}
