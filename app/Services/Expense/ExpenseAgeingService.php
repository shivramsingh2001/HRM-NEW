<?php

namespace App\Services\Expense;

use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Outstanding-advance ageing: for every employee still holding an unspent advance, HOW OLD is that money?
 *
 * The employee's ledger is replayed in time order:
 *   advance credited   -> a new lot (date, amount) joins the pool
 *   credit reversed    -> the most recent lot(s) shrink (a void undoes the latest payment)
 *   settlement debited -> consumes the OLDEST lots first (FIFO)
 * What is left are the unspent lots; their ages fall into 0-30 / 31-60 / 61-90 / 90+ day buckets.
 *
 * If an employee's stored balance is higher than the lots the ledger accounts for (a ledger that is
 * incomplete — e.g. the drift `expense:reconcile-balances` reports), the difference is shown as
 * "no ledger" rather than being silently dropped or guessed at.
 *
 * Reads with explicit tenant filters only (no global scopes), so the same code serves the web report and
 * the CLI reminder command.
 */
class ExpenseAgeingService
{
    /** bucket label => [min days, max days] */
    public const BUCKETS = ['0-30' => [0, 30], '31-60' => [31, 60], '61-90' => [61, 90], '90+' => [91, PHP_INT_MAX]];

    /**
     * @param  int[]|null  $userIds  restrict to these employees (RBAC scope); null = the whole tenant
     * @return array{rows: array<int, array>, totals: array<string, mixed>, as_of: string}
     */
    public function snapshot(int $tenantId, ?array $userIds = null, ?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ? Carbon::instance($asOf)->startOfDay() : now()->startOfDay();

        $balances = DB::table('user_expense_balances as b')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->where('b.tenant_id', $tenantId)
            ->where('b.current_balance', '>', 0)
            ->when($userIds !== null, fn ($q) => $q->whereIn('b.user_id', $userIds ?: [0]))
            ->orderBy('u.name')
            ->get(['b.user_id', 'b.current_balance', 'u.name', 'u.employee_id']);

        // Users are unique across tenants, so filtering the ledger by user id is enough (and tolerates
        // legacy ledger rows whose tenant_id was never filled in).
        $events = $balances->isEmpty() ? collect() : DB::table('expense_transactions')
            ->whereIn('user_id', $balances->pluck('user_id'))
            ->whereIn('transaction_type', ['advance_credited', 'settlement_debited'])
            ->orderBy('created_at')->orderBy('id')
            ->get(['user_id', 'transaction_type', 'amount', 'created_at'])
            ->groupBy('user_id');

        $rows = [];
        $totals = array_fill_keys(array_keys(self::BUCKETS), 0) + ['no_ledger' => 0, 'balance' => 0];

        foreach ($balances as $b) {
            $lots = $this->unsettledLots($events->get($b->user_id, collect()));
            $buckets = array_fill_keys(array_keys(self::BUCKETS), 0);
            $accounted = 0;
            $oldest = null;

            foreach ($lots as [$date, $cents]) {
                $days = (int) $date->copy()->startOfDay()->diffInDays($asOf, false);
                $days = max(0, $days);
                $oldest = $oldest === null ? $days : max($oldest, $days);
                $accounted += $cents;

                foreach (self::BUCKETS as $label => [$min, $max]) {
                    if ($days >= $min && $days <= $max) {
                        $buckets[$label] += $cents;
                        break;
                    }
                }
            }

            $balanceCents = Money::toCents($b->current_balance);
            $noLedger = max(0, $balanceCents - $accounted);

            $rows[] = [
                'user_id' => (int) $b->user_id,
                'name' => $b->name,
                'employee_id' => $b->employee_id,
                'balance' => Money::fromCents($balanceCents),
                'buckets' => array_map(fn ($c) => Money::fromCents($c), $buckets),
                'no_ledger' => Money::fromCents($noLedger),
                'oldest_days' => $oldest,
            ];

            foreach ($buckets as $label => $c) {
                $totals[$label] += $c;
            }
            $totals['no_ledger'] += $noLedger;
            $totals['balance'] += $balanceCents;
        }

        return [
            'rows' => $rows,
            'totals' => array_map(fn ($c) => Money::fromCents($c), $totals),
            'as_of' => $asOf->toDateString(),
        ];
    }

    /**
     * Replay one employee's ledger and return the unspent lots, oldest first.
     *
     * @param  Collection<int, object>  $events  advance_credited / settlement_debited rows in time order
     * @return array<int, array{0: Carbon, 1: int}>  [date, cents]
     */
    public function unsettledLots(Collection $events): array
    {
        $lots = [];

        foreach ($events as $e) {
            $cents = Money::toCents($e->amount);

            if ($e->transaction_type === 'advance_credited') {
                if ($cents > 0) {
                    $lots[] = [Carbon::parse($e->created_at), $cents];
                    continue;
                }
                // a reversal (stored negative) takes back the most recent money first
                $undo = -$cents;
                for ($i = count($lots) - 1; $i >= 0 && $undo > 0; $i--) {
                    $take = min($lots[$i][1], $undo);
                    $lots[$i][1] -= $take;
                    $undo -= $take;
                }
            } else {
                // a settlement spends the oldest advance first
                $spend = $cents;
                foreach ($lots as &$lot) {
                    if ($spend <= 0) {
                        break;
                    }
                    $take = min($lot[1], $spend);
                    $lot[1] -= $take;
                    $spend -= $take;
                }
                unset($lot);
            }

            $lots = array_values(array_filter($lots, fn ($l) => $l[1] > 0));
        }

        return $lots;
    }
}
