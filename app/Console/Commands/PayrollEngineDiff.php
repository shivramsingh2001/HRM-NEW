<?php

namespace App\Console\Commands;

use App\Models\MonthlyPayroll;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationEngine;
use Illuminate\Console\Command;

/**
 * Payroll rebuild — Phase 2 shadow-mode parity check.
 *
 * Compares the new dynamic engine's compute-only result against the
 * ALREADY-PERSISTED legacy monthly_payrolls row for the same employee+month
 * — i.e. against real historical truth — rather than re-invoking the live
 * legacy controller in parallel. This deliberately avoids touching
 * MonthlyPayrollController (2000+ lines of production payroll logic) at all
 * during Phase 2; the new engine is judged against what was actually
 * processed and paid, which is exactly what a tenant cutover needs to match.
 *
 * Modeled on the existing attendance:payroll-days-diff (PayrollDaysDiff)
 * command — same shape, same "safe to flip the flag" framing.
 */
class PayrollEngineDiff extends Command
{
    protected $signature = 'payroll:engine-diff
                            {--month= : Y-m (default: latest month with legacy payroll data)}
                            {--tenant= : restrict to one tenant}
                            {--limit=50 : users to sample}
                            {--tolerance=1.0 : ignore rupee deltas at or below this}
                            {--details : print full per-user line-item comparison, not just the summary table}';

    protected $description = 'Diff the new dynamic payroll engine against already-persisted legacy monthly_payrolls, for the same employee+month.';

    public function handle(PayrollCalculationEngine $engine): int
    {
        $month = $this->option('month') ?: MonthlyPayroll::withoutGlobalScopes()->max('payroll_month');

        if (! $month) {
            $this->error('No monthly_payrolls data exists to diff against.');

            return self::FAILURE;
        }

        $tolerance = (float) $this->option('tolerance');
        $limit = (int) $this->option('limit');
        $tenantOpt = $this->option('tenant');
        $showDetails = (bool) $this->option('details');

        $legacyRows = MonthlyPayroll::withoutGlobalScopes()
            ->where('payroll_month', $month)
            ->when($tenantOpt, fn ($q) => $q->where('tenant_id', $tenantOpt))
            ->limit($limit)
            ->get();

        $this->info("Month {$month} — {$legacyRows->count()} legacy payslips to compare (tolerance ₹{$tolerance})");

        $compared = 0;
        $skippedNoStructure = 0;
        $driftRows = [];

        foreach ($legacyRows as $legacy) {
            $employee = User::withoutGlobalScopes()->find($legacy->user_id);

            if (! $employee) {
                continue;
            }

            try {
                $new = $engine->calculate($employee, (int) $legacy->tenant_id, $month);
            } catch (\Throwable $e) {
                $skippedNoStructure++;
                if ($showDetails) {
                    $this->warn("  user {$legacy->user_id}: new engine failed — " . $e->getMessage());
                }
                continue;
            }

            $compared++;

            $deltas = [];
            foreach ([
                'gross_earnings' => (float) $legacy->gross_earnings,
                'total_deductions' => (float) $legacy->total_deductions,
                'net_payable' => (float) $legacy->net_payable,
            ] as $field => $legacyValue) {
                $newValue = (float) $new[$field];
                if (abs($legacyValue - $newValue) > $tolerance) {
                    $deltas[] = "{$field}: legacy=" . number_format($legacyValue, 2) . ' new=' . number_format($newValue, 2);
                }
            }

            if ($showDetails) {
                $this->line("--- user {$legacy->user_id} ({$employee->name}) ---");
                $this->line('  legacy: gross=' . $legacy->gross_earnings . ' deductions=' . $legacy->total_deductions . ' net=' . $legacy->net_payable);
                $this->line('  new:    gross=' . $new['gross_earnings'] . ' deductions=' . $new['total_deductions'] . ' net=' . $new['net_payable']);
                foreach ($new['line_items'] as $li) {
                    $this->line("    {$li['code']} ({$li['component_type']}): {$li['amount']}");
                }
            }

            if ($deltas) {
                $driftRows[] = [$legacy->user_id, $employee->name, implode('; ', $deltas)];
            }
        }

        $this->info("Compared: {$compared}, skipped (no dynamic structure / engine error): {$skippedNoStructure}");

        if ($driftRows) {
            $this->table(['User', 'Name', 'Drift'], $driftRows);
            $this->warn(count($driftRows) . " of {$compared} drifted beyond tolerance.");

            return self::FAILURE;
        }

        if ($compared === 0) {
            $this->warn('Nothing was actually compared — check that Phase 1 backfill has run for this tenant/month.');

            return self::FAILURE;
        }

        $this->info('No material drift — dynamic engine matches legacy for every compared payslip.');

        return self::SUCCESS;
    }
}
