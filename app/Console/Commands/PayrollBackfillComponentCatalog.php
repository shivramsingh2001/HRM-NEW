<?php

namespace App\Console\Commands;

use App\Models\PayrollComponentMaster;
use App\Models\PayrollComponentTemplate;
use App\Models\PayrollEmployeeComponent;
use App\Models\PayrollEmployeeStructure;
use App\Models\Tenant;
use App\Models\UserPayroll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Payroll rebuild — Phase 1 one-time backfill.
 *
 * Step 1: seeds the platform payroll_component_templates catalog (idempotent,
 *         upserts by code — safe to re-run if the standard set changes).
 * Step 2: clones the templates into each tenant's own payroll_component_master
 *         row, but ONLY if that tenant doesn't already have a component with
 *         that code (never overwrites a tenant's existing customization).
 * Step 3: converts every user_payrolls row into a payroll_employee_structures
 *         + payroll_employee_components snapshot (source='backfill_v1'), a
 *         direct copy of the historical flat values — never an inferred
 *         percentage relationship. Idempotent via the existing unique
 *         constraint on (tenant_id, user_id, effective_from): a row that
 *         already exists for that date is skipped, not duplicated.
 *
 * Never touches monthly_payrolls, user_payrolls, or payroll_masters.
 */
class PayrollBackfillComponentCatalog extends Command
{
    protected $signature = 'payroll:backfill-component-catalog
                            {--tenant= : restrict to one tenant id}
                            {--dry-run : report what would happen, write nothing}';

    protected $description = 'Seed the payroll component catalog and convert existing user_payrolls rows into the new dynamic structure/component tables.';

    /**
     * Fixed-column -> catalog-component mapping. Order matters: earnings
     * before deductions/employer contributions so a future percentage-based
     * template (e.g. HRA = % of Basic) has its base already defined at a
     * lower priority.
     */
    private const COMPONENT_TEMPLATES = [
        // code => [name, type, statutory_type, source user_payrolls column, priority]
        ['basic',                   'Basic Salary',          'earning',               null,   'basic_salary',            10],
        ['hra',                     'HRA',                   'earning',               null,   'hra',                     20],
        ['conveyance',              'Conveyance',            'earning',               null,   'conveyence',              20],
        ['medical_allowance',       'Medical Allowance',     'earning',               null,   'medical_allowance',       20],
        ['children_allowance',      'Children Allowance',    'earning',               null,   'children_allowance',      20],
        ['post_allowance',          'Post Allowance',        'earning',               null,   'post_allowance',          20],
        ['leave_travel_allowance',  'Leave Travel Allowance', 'earning',              null,   'leave_travel_allowance',  20],
        ['monthly_incentive',       'Monthly Incentive',     'earning',               null,   'monthly_incentive',       20],
        ['special_allowance',       'Special Allowance',     'earning',               null,   'special_allowance',       20],
        ['arrears',                 'Arrears',               'earning',               null,   null,                      25],
        ['bonus',                   'Bonus',                 'earning',               null,   null,                      25],
        ['pf_employee',             'Provident Fund (Employee)', 'deduction',         'pf',   'provident_fund',          40],
        ['esi_employee',            'ESI (Employee)',        'deduction',             'esi',  'esi',                     40],
        ['pt',                      'Professional Tax',      'deduction',             'pt',   'professional_tax',        40],
        ['tds',                     'TDS',                   'deduction',             'tds',  'tds',                     40],
        ['pf_employer',             'Provident Fund (Employer)', 'employer_contribution', 'pf', 'employer_provident_fund', 40],
        ['esi_employer',            'ESI (Employer)',        'employer_contribution', 'esi', 'employer_esi',             40],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $tenantOpt = $this->option('tenant');

        $tenants = Tenant::when($tenantOpt, fn ($q) => $q->where('id', $tenantOpt))->get();
        $this->info("Tenants to process: {$tenants->count()}");

        $totalClonedComponents = 0;
        $totalStructures = 0;
        $totalComponents = 0;
        $totalSkippedExisting = 0;

        // Everything — including the template seed — must happen inside the
        // same transaction so --dry-run's rollback actually undoes all of
        // it, not just Steps 2/3.
        DB::beginTransaction();

        try {
            $this->info('=== Step 1: seeding payroll_component_templates ===' . ($dry ? '  [DRY RUN]' : ''));
            $this->seedTemplates($dry);

            foreach ($tenants as $tenant) {
                $this->line("--- Tenant #{$tenant->id} ({$tenant->company_name}) ---");

                $totalClonedComponents += $this->cloneTemplatesForTenant($tenant->id);

                [$structures, $components, $skipped] = $this->backfillEmployeeStructures($tenant->id);
                $totalStructures += $structures;
                $totalComponents += $components;
                $totalSkippedExisting += $skipped;
            }

            $this->info('=== Summary ===');
            $this->info("Components cloned into tenant catalogs: {$totalClonedComponents}");
            $this->info("payroll_employee_structures created: {$totalStructures}");
            $this->info("payroll_employee_components created: {$totalComponents}");
            $this->info("user_payrolls rows skipped (already backfilled): {$totalSkippedExisting}");

            if ($dry) {
                DB::rollBack();
                $this->warn('DRY RUN — nothing was written, transaction rolled back.');
            } else {
                DB::commit();
                $this->info('Committed.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Backfill failed, rolled back: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function seedTemplates(bool $dry): void
    {
        $count = 0;

        foreach (self::COMPONENT_TEMPLATES as [$code, $name, $type, $statutoryType, $sourceColumn, $priority]) {
            $exists = PayrollComponentTemplate::where('code', $code)->exists();

            if ($exists) {
                continue;
            }

            $count++;

            // Written even on --dry-run: the outer transaction in handle()
            // rolls everything back at the end, but Steps 2/3 need these
            // rows to exist transiently in order to preview accurately.
            PayrollComponentTemplate::create([
                'code' => $code,
                'name' => $name,
                'component_type' => $type,
                'is_statutory' => (bool) $statutoryType,
                'statutory_type' => $statutoryType,
                'calculation_method' => 'fixed_amount',
                'calculation_base_type' => 'none',
                'default_amount' => 0,
                'priority' => $priority,
                'proration_rule' => in_array($code, ['arrears', 'bonus'], true) ? 'no_proration' : 'prorate_by_payable_days',
                'is_taxable' => $type !== 'employer_contribution',
                'has_wage_ceiling' => false,
                'affects_gross' => $type !== 'employer_contribution',
                'affects_ctc' => true,
                'affects_net' => $type !== 'employer_contribution',
                'display_order' => $priority,
                'is_offered_to_new_tenants' => true,
            ]);
        }

        $this->line("Templates " . ($dry ? 'to seed' : 'seeded') . ": {$count} (of " . count(self::COMPONENT_TEMPLATES) . ' total)');
    }

    private function cloneTemplatesForTenant(int $tenantId): int
    {
        $templates = PayrollComponentTemplate::all();
        $cloned = 0;

        foreach ($templates as $template) {
            $exists = PayrollComponentMaster::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('code', $template->code)
                ->exists();

            if ($exists) {
                continue;
            }

            PayrollComponentMaster::create([
                'tenant_id' => $tenantId,
                'code' => $template->code,
                'name' => $template->name,
                'component_type' => $template->component_type,
                'is_statutory' => $template->is_statutory,
                'statutory_type' => $template->statutory_type,
                'calculation_method' => $template->calculation_method,
                'calculation_base_type' => $template->calculation_base_type,
                'calculation_base' => $template->calculation_base,
                'percentage_value' => $template->percentage_value,
                'default_amount' => $template->default_amount,
                'priority' => $template->priority,
                'proration_rule' => $template->proration_rule,
                'is_taxable' => $template->is_taxable,
                'has_wage_ceiling' => $template->has_wage_ceiling,
                'ceiling_amount' => $template->ceiling_amount,
                'ceiling_apply_rule' => $template->ceiling_apply_rule,
                'eligibility_rules' => $template->eligibility_rules,
                'affects_gross' => $template->affects_gross,
                'affects_ctc' => $template->affects_ctc,
                'affects_net' => $template->affects_net,
                'display_order' => $template->display_order,
                'is_active' => true,
                'is_system_default' => true,
            ]);

            $cloned++;
        }

        $this->line("  Components cloned: {$cloned}");

        return $cloned;
    }

    /**
     * @return array{0:int,1:int,2:int} [structures created, components created, skipped]
     */
    private function backfillEmployeeStructures(int $tenantId): array
    {
        // Map of code -> PayrollComponentMaster id for this tenant, built once.
        $componentIds = PayrollComponentMaster::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'code');

        $structuresCreated = 0;
        $componentsCreated = 0;
        $skipped = 0;

        $userPayrolls = UserPayroll::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderBy('user_id')
            ->orderBy('effective_from')
            ->get();

        foreach ($userPayrolls as $userPayroll) {
            $alreadyBackfilled = PayrollEmployeeStructure::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userPayroll->user_id)
                ->where('effective_from', $userPayroll->effective_from)
                ->exists();

            if ($alreadyBackfilled) {
                $skipped++;
                continue;
            }

            $structure = PayrollEmployeeStructure::create([
                'tenant_id' => $tenantId,
                'user_id' => $userPayroll->user_id,
                'payroll_structure_id' => null,
                'effective_from' => $userPayroll->effective_from,
                'effective_to' => $userPayroll->effective_to,
                'is_current' => (bool) $userPayroll->is_current,
                'ctc' => $userPayroll->ctc,
                'revision_type' => 'initial',
                'revision_reason' => 'Backfilled from legacy user_payrolls#' . $userPayroll->id,
                'status' => $userPayroll->is_current ? 'active' : 'superseded',
                'source' => 'backfill_v1',
                'created_by' => null,
                'notes' => 'Auto-generated by payroll:backfill-component-catalog',
            ]);

            $structuresCreated++;

            foreach (self::COMPONENT_TEMPLATES as [$code, , , , $sourceColumn, ]) {
                if (! $sourceColumn) {
                    continue; // arrears/bonus have no historical column to snapshot
                }

                $value = (float) ($userPayroll->{$sourceColumn} ?? 0);

                if ($value == 0.0 || ! isset($componentIds[$code])) {
                    continue;
                }

                PayrollEmployeeComponent::create([
                    'tenant_id' => $tenantId,
                    'payroll_employee_structure_id' => $structure->id,
                    'payroll_component_master_id' => $componentIds[$code],
                    'calculation_method' => 'fixed_amount',
                    'calculation_base_type' => 'none',
                    'amount' => $value,
                    'is_enabled' => true,
                ]);

                $componentsCreated++;
            }
        }

        $this->line("  Structures: {$structuresCreated}, Components: {$componentsCreated}, Skipped (already backfilled): {$skipped}");

        return [$structuresCreated, $componentsCreated, $skipped];
    }
}
