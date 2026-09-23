<?php

namespace App\Console\Commands;

use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Expense consistency check. READ-ONLY unless a --fix flag is passed.
 *
 *  1. Balances: recompute every employee's balances from the transaction ledger
 *       advance       = SUM(advance_credited)      (reversals are stored as negative rows)
 *       settlement    = SUM(settlement_debited)
 *       current       = advance - settlement
 *       reimbursement = SUM(reimbursement_paid)
 *     and compare with user_expense_balances.
 *  2. paid_amount: recompute expenses.paid_amount from expense_payments.
 *  3. Flags (never auto-fixed): overpaid expenses, and status that disagrees with
 *     the paid amount (advance/reimbursement `complete` but underpaid, or `approved`
 *     but fully paid).
 *
 * Why it exists: before Phase 1 nothing locked balance updates, so concurrent
 * approvals/payments could have left the cached balances out of step with the
 * ledger. Run this (dry) before relying on the numbers, e.g. before Phase 3.
 *
 * --fix           rewrites ONLY expenses.paid_amount (a derived cache — always safe).
 * --fix-balances  ALSO overwrites user_expense_balances from the ledger. Only correct
 *                 if the ledger is complete for that employee; review the report first.
 *
 * Exit code: 0 = consistent, 1 = drift or inconsistencies remain.
 */
class ExpenseReconcileBalances extends Command
{
    protected $signature = 'expense:reconcile-balances
        {--tenant= : Only this tenant id}
        {--fix : Rewrite expenses.paid_amount from the payments table}
        {--fix-balances : Also overwrite user_expense_balances from the ledger (review the report first)}';

    protected $description = 'Report (and optionally repair) drift between expense balances/paid amounts and their ledgers';

    public function handle(): int
    {
        $tenant = $this->option('tenant');
        $fix = (bool) $this->option('fix');
        $fixBalances = (bool) $this->option('fix-balances');

        $problems = 0;

        // ---------------- 1. balances vs ledger ----------------
        $ledger = DB::table('expense_transactions')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant))
            ->selectRaw("user_id, MAX(tenant_id) as tenant_id,
                COALESCE(SUM(CASE WHEN transaction_type = 'advance_credited'   THEN amount END), 0) AS adv,
                COALESCE(SUM(CASE WHEN transaction_type = 'settlement_debited' THEN amount END), 0) AS setl,
                COALESCE(SUM(CASE WHEN transaction_type = 'reimbursement_paid' THEN amount END), 0) AS reimb")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $balances = DB::table('user_expense_balances')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant))
            ->get()
            ->keyBy('user_id');

        $balanceDrift = [];
        foreach ($ledger->keys()->merge($balances->keys())->unique() as $userId) {
            $l = $ledger->get($userId);
            $b = $balances->get($userId);

            $expected = [
                'advance' => Money::toCents($l->adv ?? 0),
                'settlement' => Money::toCents($l->setl ?? 0),
                'reimbursement' => Money::toCents($l->reimb ?? 0),
            ];
            $expected['current'] = $expected['advance'] - $expected['settlement'];

            $actual = [
                'advance' => Money::toCents($b->advance_balance ?? 0),
                'settlement' => Money::toCents($b->settlement_balance ?? 0),
                'reimbursement' => Money::toCents($b->reimbursement_balance ?? 0),
                'current' => Money::toCents($b->current_balance ?? 0),
            ];

            if ($expected === $actual) {
                continue;
            }

            $balanceDrift[] = [
                'user_id' => $userId,
                'tenant_id' => $b->tenant_id ?? $l->tenant_id ?? null,
                'has_balance_row' => $b ? 'yes' : 'NO',
                'stored (cur/adv/set/reimb)' => $this->fmt($actual),
                'ledger (cur/adv/set/reimb)' => $this->fmt($expected),
                '_expected' => $expected,
                '_has_row' => (bool) $b,
            ];
        }

        if ($balanceDrift) {
            $this->warn(count($balanceDrift) . ' employee balance(s) differ from their ledger:');
            $this->table(
                ['user_id', 'tenant_id', 'has_balance_row', 'stored (cur/adv/set/reimb)', 'ledger (cur/adv/set/reimb)'],
                array_map(fn ($r) => array_slice($r, 0, 5), $balanceDrift)
            );

            if ($fixBalances) {
                foreach ($balanceDrift as $row) {
                    if (! $row['_has_row']) {
                        $this->line("user {$row['user_id']}: no balance row to overwrite — skipped");
                        $problems++;
                        continue;
                    }
                    $e = $row['_expected'];
                    DB::table('user_expense_balances')->where('user_id', $row['user_id'])->update([
                        'advance_balance' => Money::fromCents($e['advance']),
                        'settlement_balance' => Money::fromCents($e['settlement']),
                        'reimbursement_balance' => Money::fromCents($e['reimbursement']),
                        'current_balance' => Money::fromCents($e['current']),
                        'updated_at' => now(),
                    ]);
                }
                $this->info('Balances overwritten from the ledger (--fix-balances).');
            } else {
                $problems += count($balanceDrift);
                $this->line('Not changed. Re-run with --fix-balances to overwrite from the ledger (only if the ledger is complete).');
            }
        } else {
            $this->info('Balances: all employees match their ledger.');
        }

        // ---------------- 2. paid_amount vs payments ----------------
        $paid = DB::table('expenses as e')
            // POSTED payments only — a voided payment no longer counts toward what was paid.
            ->leftJoin('expense_payments as p', fn ($join) => $join->on('p.expense_id', '=', 'e.id')->where('p.status', 'posted'))
            ->when($tenant, fn ($q) => $q->where('e.tenant_id', $tenant))
            ->groupBy('e.id', 'e.tenant_id', 'e.expense_number', 'e.requirement_type', 'e.status', 'e.amount', 'e.paid_amount')
            ->selectRaw('e.id, e.tenant_id, e.expense_number, e.requirement_type, e.status, e.amount, e.paid_amount, COALESCE(SUM(p.amount), 0) AS actual')
            ->get();

        $paidDrift = $paid->filter(fn ($r) => Money::toCents($r->paid_amount) !== Money::toCents($r->actual))->values();

        if ($paidDrift->isNotEmpty()) {
            $this->warn($paidDrift->count() . ' expense(s) have a stale paid_amount:');
            $this->table(['id', 'number', 'stored paid_amount', 'actual (payments)'],
                $paidDrift->map(fn ($r) => [$r->id, $r->expense_number, $r->paid_amount, $r->actual])->all());

            if ($fix || $fixBalances) {
                foreach ($paidDrift as $r) {
                    DB::table('expenses')->where('id', $r->id)->update(['paid_amount' => Money::fromCents(Money::toCents($r->actual))]);
                }
                $this->info('paid_amount rewritten from payments (--fix).');
            } else {
                $problems += $paidDrift->count();
                $this->line('Not changed. Re-run with --fix to rewrite paid_amount from the payments table.');
            }
        } else {
            $this->info('paid_amount: all expenses match their payments.');
        }

        // ---------------- 3. inconsistencies we never auto-fix ----------------
        $flags = [];
        foreach ($paid as $r) {
            if (! in_array($r->requirement_type, ['advance', 'reimbursement'], true)) {
                continue;
            }
            $amount = Money::toCents($r->amount);
            $actual = Money::toCents($r->actual);

            if ($actual > $amount) {
                $flags[] = [$r->id, $r->expense_number, 'OVERPAID', "amount {$r->amount}, paid {$r->actual}"];
            } elseif ($r->status === 'complete' && $actual < $amount && ! $this->isDirect($r->id)) {
                $flags[] = [$r->id, $r->expense_number, 'complete but underpaid', "amount {$r->amount}, paid {$r->actual}"];
            } elseif ($r->status === 'approved' && $actual === $amount) {
                $flags[] = [$r->id, $r->expense_number, 'approved but fully paid', "amount {$r->amount}, paid {$r->actual}"];
            }
        }

        if ($flags) {
            $this->warn(count($flags) . ' expense(s) need manual review (never auto-fixed):');
            $this->table(['id', 'number', 'issue', 'detail'], $flags);
            $problems += count($flags);
        } else {
            $this->info('Statuses: no overpaid or status/payment mismatches.');
        }

        $this->newLine();
        $this->line($problems === 0 ? '<info>Consistent.</info>' : "<comment>{$problems} unresolved item(s).</comment>");

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array{current:int, advance:int, settlement:int, reimbursement:int} $c */
    private function fmt(array $c): string
    {
        return implode(' / ', array_map(fn ($k) => Money::format($c[$k]), ['current', 'advance', 'settlement', 'reimbursement']));
    }

    /** Legacy "direct payment" stand-ins were created already complete; skip them in the underpaid check. */
    private function isDirect(int $expenseId): bool
    {
        return DB::table('expenses')->where('id', $expenseId)->where('is_direct_payment', 1)->exists();
    }
}
