<?php

namespace App\Console\Commands;

use App\Models\MonthlyPayroll;
use App\Models\PayrollComponentMaster;
use App\Models\PayrollComponentTemplate;
use App\Models\PayrollEmployeeStructure;
use App\Models\Tenant;
use App\Models\UserPayroll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Payroll Audit Phase 2 — C7. The single, safe on-ramp for switching a
 * tenant onto the dynamic payroll engine, instead of a raw
 * `UPDATE tenants SET payroll_dynamic_ui_enabled=1` or a hand-written SQL
 * migration script.
 *
 * Deliberately does NOT re-implement the backfill/resync/diff logic — it
 * checks (read-only) whether `payroll:backfill-component-catalog` and
 * `payroll:resync-drifted-structures` have already been run cleanly for
 * this tenant, and shells out to `payroll:engine-diff` (via Artisan::call,
 * its own exit code) for the parity check against any real payroll
 * history. Only flips the flag if every check passes.
 *
 * Never touches monthly_payrolls — historical, already-paid payslips stay
 * on the legacy engine permanently. Only new payroll runs after cutover use
 * the dynamic engine. Rollback is just flipping the flag back to 0; no data
 * is ever deleted by this command.
 */
class PayrollCutoverTenant extends Command
{
    protected $signature = 'payroll:cutover-tenant
                            {tenant : tenant id}
                            {--dry-run : report readiness without flipping the flag}
                            {--tolerance=1.0 : rupee tolerance passed through to payroll:engine-diff}';

    protected $description = 'Safely switch one tenant onto the dynamic payroll engine, after verifying the component catalog, structures, and (if applicable) engine-diff parity are all clean.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $tenantId = (int) $this->argument('tenant');

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (! $tenant) {
            $this->error("Tenant #{$tenantId} not found.");

            return self::FAILURE;
        }

        $this->info("Tenant #{$tenant->id} ({$tenant->company_name})" . ($dry ? '  [DRY RUN]' : ''));

        if ($tenant->payroll_dynamic_ui_enabled) {
            $this->warn('Already on the dynamic engine — nothing to do.');

            return self::SUCCESS;
        }

        $failures = [];

        $catalogGap = $this->checkComponentCatalog($tenantId);
        if ($catalogGap !== null) {
            $failures[] = $catalogGap;
        }

        $driftedCount = $this->checkDriftedStructures($tenantId);
        if ($driftedCount > 0) {
            $failures[] = "{$driftedCount} employee(s) have a current legacy salary with no matching current dynamic "
                . "structure. Run: php artisan payroll:resync-drifted-structures --tenant={$tenantId}";
        }

        $hasHistory = MonthlyPayroll::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists();
        if ($hasHistory) {
            $this->line('Legacy payroll history found — running payroll:engine-diff for a parity check...');

            $diffExit = Artisan::call('payroll:engine-diff', [
                '--tenant' => $tenantId,
                '--tolerance' => (float) $this->option('tolerance'),
            ]);

            $this->line(Artisan::output());

            if ($diffExit !== self::SUCCESS) {
                $failures[] = 'payroll:engine-diff reported drift beyond tolerance (or nothing comparable) — review the '
                    . 'output above before cutting over.';
            }
        } else {
            $this->line('No legacy payroll history for this tenant — skipping engine-diff (nothing to compare against).');
        }

        if ($failures) {
            $this->error('Not ready to cut over:');
            foreach ($failures as $f) {
                $this->line('  - ' . $f);
            }

            return self::FAILURE;
        }

        $this->info('All checks passed.');

        if ($dry) {
            $this->warn('DRY RUN — flag not changed.');

            return self::SUCCESS;
        }

        // Tenant::$fillable deliberately doesn't include this column (it's
        // not meant to be settable from any HTTP request path in this app —
        // today only the separate super-admin panel or direct DB access
        // would otherwise flip it) — forceFill() bypasses mass-assignment
        // protection for this one deliberate, non-request-driven write
        // without weakening $fillable for every other write path.
        $tenant->forceFill(['payroll_dynamic_ui_enabled' => true])->save();

        Log::channel('daily')->info('Tenant cut over to the dynamic payroll engine.', [
            'tenant_id' => $tenantId,
            'company_name' => $tenant->company_name,
            'cutover_by' => $this->getLaravel()->runningInConsole() ? 'artisan:payroll:cutover-tenant' : null,
        ]);

        $this->info("Tenant #{$tenantId} is now on the dynamic payroll engine.");

        return self::SUCCESS;
    }

    /**
     * Returns a human-readable gap description, or null if the catalog is
     * complete for this tenant (mirrors the code set
     * PayrollBackfillComponentCatalog clones from payroll_component_templates).
     */
    private function checkComponentCatalog(int $tenantId): ?string
    {
        $templateCodes = PayrollComponentTemplate::pluck('code');

        if ($templateCodes->isEmpty()) {
            return 'payroll_component_templates is empty — run: php artisan payroll:backfill-component-catalog first.';
        }

        $tenantCodes = PayrollComponentMaster::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->pluck('code');

        $missing = $templateCodes->diff($tenantCodes);

        if ($missing->isNotEmpty()) {
            return 'Component catalog missing codes [' . $missing->implode(', ') . "] for this tenant. Run: "
                . "php artisan payroll:backfill-component-catalog --tenant={$tenantId}";
        }

        return null;
    }

    /**
     * Same drift definition as PayrollResyncDriftedStructures: a user whose
     * legacy "current" salary has no matching current dynamic structure.
     */
    private function checkDriftedStructures(int $tenantId): int
    {
        $legacyCurrentUserIds = UserPayroll::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->pluck('user_id');

        $dynamicCurrentUserIds = PayrollEmployeeStructure::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->pluck('user_id');

        return $legacyCurrentUserIds->diff($dynamicCurrentUserIds)->count();
    }
}
