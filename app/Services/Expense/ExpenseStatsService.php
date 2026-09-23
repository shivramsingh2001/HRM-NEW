<?php

namespace App\Services\Expense;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Expense summary cards — ONE grouped SQL query (GROUP BY requirement_type,
 * status) instead of loading every expense into PHP and filtering the collection
 * ~80 times (which is what index(), view_all() and the mobile API each did).
 *
 * Both web screens and the API feed this the same already-filtered builder, so
 * the cards keep reflecting whatever filters the user has applied.
 *
 * DEFINITION (product decision, 2026-09-21): the combined "Total" and the
 * combined pending / approved / completed / cancelled cards mean
 * settlement + reimbursement — actual spend. An advance is cash handed out before
 * the spending happens (it is later consumed by a settlement), so counting it too
 * double-counts. Advance figures stay available as their own cards. Previously
 * "My Expenses" counted settlement+reimbursement while the manager "View All"
 * page counted advance+reimbursement.
 */
class ExpenseStatsService
{
    /** Blade-variable label => requirement_type */
    public const TYPES = ['Advance' => 'advance', 'Settlement' => 'settlement', 'Reimbursement' => 'reimbursement'];

    /** Blade-variable label => expenses.status ("completed" is the 'complete' enum value) */
    public const STATUSES = ['pending' => 'pending', 'approved' => 'approved', 'completed' => 'complete', 'cancelled' => 'cancelled'];

    /** requirement_types that count as actual spend in the combined cards. */
    public const SPEND_TYPES = ['settlement', 'reimbursement'];

    /**
     * @param  EloquentBuilder|QueryBuilder  $filtered  an expenses query with all filters applied (ordering/eager-loads are ignored)
     * @return array<string, array<string, array{count:int, cents:int}>>  [requirement_type][status]
     */
    public function matrix(EloquentBuilder|QueryBuilder $filtered): array
    {
        $base = $filtered instanceof EloquentBuilder
            ? $filtered->clone()->reorder()->toBase()
            : (clone $filtered)->reorder();

        // A reimbursement split off a settlement's shortfall is part of that settlement's spend, which the
        // settlement already counts in full — counting the child too would overstate the cards.
        $rows = $base
            ->whereNull('expenses.parent_expense_id')
            ->select(['expenses.requirement_type as req_type', 'expenses.status as st'])
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(expenses.amount), 0) as total')
            ->groupBy('expenses.requirement_type', 'expenses.status')
            ->get();

        $matrix = [];
        foreach (self::TYPES as $type) {
            foreach (self::STATUSES as $status) {
                $matrix[$type][$status] = ['count' => 0, 'cents' => 0];
            }
        }

        foreach ($rows as $row) {
            if (isset($matrix[$row->req_type][$row->st])) {
                $matrix[$row->req_type][$row->st] = ['count' => (int) $row->c, 'cents' => Money::toCents($row->total)];
            }
        }

        return $matrix;
    }

    /**
     * The flat variable set the two Blade views already use (totalAdvanceCount,
     * pendingSettlementAmount, completedCount, ...), so the views need no change.
     *
     * @param  array<string, array<string, array{count:int, cents:int}>>  $m
     * @return array<string, int|float>
     */
    public function viewData(array $m): array
    {
        $out = [];

        foreach (self::TYPES as $label => $type) {
            [$count, $cents] = $this->sum($m, [$type], array_values(self::STATUSES));
            $out["total{$label}Count"] = $count;
            $out["total{$label}Amount"] = Money::fromCents($cents);

            foreach (self::STATUSES as $sLabel => $status) {
                [$count, $cents] = $this->sum($m, [$type], [$status]);
                $out["{$sLabel}{$label}Count"] = $count;
                $out["{$sLabel}{$label}Amount"] = Money::fromCents($cents);
            }
        }

        // Combined cards = actual spend (settlement + reimbursement).
        [$count, $cents] = $this->sum($m, self::SPEND_TYPES, array_values(self::STATUSES));
        $out['totalExpenses'] = $count;
        $out['totalAmount'] = Money::fromCents($cents);

        foreach (self::STATUSES as $sLabel => $status) {
            [$count, $cents] = $this->sum($m, self::SPEND_TYPES, [$status]);
            $out["{$sLabel}Count"] = $count;
            $out["{$sLabel}Amount"] = Money::fromCents($cents);
        }

        return $out;
    }

    /**
     * Nested shape for the mobile API `statistics` block (the shape the old,
     * commented-out block described): per type, plus a combined by_status.
     *
     * @return array<string, mixed>
     */
    public function apiSummary(array $m): array
    {
        $summary = [];

        foreach (self::TYPES as $type) {
            [$count, $cents] = $this->sum($m, [$type], array_values(self::STATUSES));
            $summary[$type] = ['total_count' => $count, 'total_amount' => Money::fromCents($cents)];

            foreach (self::STATUSES as $sLabel => $status) {
                [$count, $cents] = $this->sum($m, [$type], [$status]);
                $summary[$type][$sLabel] = ['count' => $count, 'amount' => Money::fromCents($cents)];
            }
        }

        [$count, $cents] = $this->sum($m, self::SPEND_TYPES, array_values(self::STATUSES));
        $summary['spend'] = ['count' => $count, 'amount' => Money::fromCents($cents)];

        foreach (self::STATUSES as $sLabel => $status) {
            [$count, $cents] = $this->sum($m, self::SPEND_TYPES, [$status]);
            $summary['by_status'][$sLabel] = ['count' => $count, 'amount' => Money::fromCents($cents)];
        }

        return $summary;
    }

    /** @return array{0:int, 1:int} [count, cents] summed over the given types x statuses */
    private function sum(array $m, array $types, array $statuses): array
    {
        $count = 0;
        $cents = 0;

        foreach ($types as $type) {
            foreach ($statuses as $status) {
                $count += $m[$type][$status]['count'] ?? 0;
                $cents += $m[$type][$status]['cents'] ?? 0;
            }
        }

        return [$count, $cents];
    }
}
