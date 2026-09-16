<?php

namespace App\Console\Commands;

use App\Models\PayrollComponentMaster;
use App\Models\PayrollComponentTemplate;
use App\Models\StatutoryPtSlab;
use App\Models\StatutoryRateConfig;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Payroll rebuild — Phase 4.
 *
 * Seeds sensible, well-known statutory defaults (India-wide PF/ESI rates,
 * and Professional Tax slabs for the states that actually levy PT) into
 * every tenant's own statutory_rate_configs / statutory_pt_slabs rows so
 * the new Compliance Settings screen isn't empty on first visit. Tenant
 * admins can then edit/override these — nothing here is hardcoded into the
 * calculation engine itself, only into these seeded, editable rows.
 *
 * Idempotent: skips a tenant+type/state that already has a config row.
 */
class PayrollSeedStatutoryDefaults extends Command
{
    protected $signature = 'payroll:seed-statutory-defaults {--tenant= : restrict to one tenant}';

    protected $description = 'Seed default PF/ESI rate configs and Professional Tax slabs for tenants.';

    /** States that actually levy Professional Tax, with their standard monthly slabs. */
    private const PT_SLABS = [
        'Karnataka' => [
            [0, 15000, 0],
            [15000.01, 24999.99, 200],
            [25000, null, 300],
        ],
        'Maharashtra' => [
            [0, 7500, 0],
            [7500.01, 10000, 175],
            [10000.01, null, 200],
        ],
        'West Bengal' => [
            [0, 10000, 0],
            [10000.01, 15000, 110],
            [15000.01, 25000, 130],
            [25000.01, 40000, 150],
            [40000.01, null, 200],
        ],
        'Telangana' => [
            [0, 15000, 0],
            [15000.01, 20000, 150],
            [20000.01, null, 200],
        ],
        'Andhra Pradesh' => [
            [0, 15000, 0],
            [15000.01, 20000, 150],
            [20000.01, null, 200],
        ],
        'Gujarat' => [
            [0, 12000, 0],
            [12000.01, null, 200],
        ],
    ];

    /**
     * Correct wage-ceiling metadata for the PF/ESI components created by
     * the Phase 1 backfill: it seeded has_wage_ceiling=false / no
     * ceiling_apply_rule for every component (a bug found while wiring the
     * Phase 4 engine override, since PayrollCalculationEngine defaults to
     * PF-style "cap the base" whenever ceiling_apply_rule isn't explicitly
     * 'ceiling_exclude' — ESI needs the exclude behavior, not a cap).
     * Idempotent/always-correct to re-apply since these are the objective
     * statutory ceiling rules, not tenant preference.
     */
    private const CEILING_FIXES = [
        'pf_employee' => ['ceiling_amount' => 15000, 'ceiling_apply_rule' => 'cap_base_before_percentage'],
        'pf_employer' => ['ceiling_amount' => 15000, 'ceiling_apply_rule' => 'cap_base_before_percentage'],
        'esi_employee' => ['ceiling_amount' => 21000, 'ceiling_apply_rule' => 'ceiling_exclude'],
        'esi_employer' => ['ceiling_amount' => 21000, 'ceiling_apply_rule' => 'ceiling_exclude'],
    ];

    /**
     * Professional Tax is a fixed monthly statutory amount for a gross-salary
     * bracket -- unlike an allowance, it is not reduced for a partial month.
     * The Phase 1 backfill defaulted every non-arrears/bonus component to
     * prorate_by_payable_days, which silently shrank PT for anyone with
     * imperfect attendance. TDS is deliberately left alone here (still
     * flat/manual per the confirmed scope decision, and its proration is a
     * judgment call this fix doesn't need to make).
     */
    private const PRORATION_FIXES = [
        'pt' => 'no_proration',
    ];

    public function handle(): int
    {
        $tenantOpt = $this->option('tenant');
        $tenants = Tenant::when($tenantOpt, fn ($q) => $q->where('id', $tenantOpt))->get();

        $this->fixCeilingMetadata($tenantOpt);

        $rateConfigsSeeded = 0;
        $slabsSeeded = 0;

        foreach ($tenants as $tenant) {
            foreach (['pf', 'esi'] as $type) {
                $exists = StatutoryRateConfig::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->where('statutory_type', $type)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $config = $type === 'pf'
                    ? ['employee_rate' => 12.0, 'employer_rate' => 12.0, 'wage_ceiling' => 15000]
                    : ['employee_rate' => 0.75, 'employer_rate' => 3.25, 'wage_ceiling' => 21000];

                StatutoryRateConfig::create([
                    'tenant_id' => $tenant->id,
                    'statutory_type' => $type,
                    'region_code' => null,
                    'effective_from' => '2020-01-01',
                    'effective_to' => null,
                    'config' => $config,
                    'created_by' => null,
                ]);

                $rateConfigsSeeded++;
            }

            $state = $tenant->state;
            if ($state && isset(self::PT_SLABS[$state])) {
                $alreadySeeded = StatutoryPtSlab::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->where('state_code', $state)
                    ->exists();

                if (! $alreadySeeded) {
                    foreach (self::PT_SLABS[$state] as [$min, $max, $amount]) {
                        StatutoryPtSlab::create([
                            'tenant_id' => $tenant->id,
                            'state_code' => $state,
                            'effective_from' => '2020-01-01',
                            'effective_to' => null,
                            'gross_salary_min' => $min,
                            'gross_salary_max' => $max,
                            'pt_amount' => $amount,
                            'gender' => 'all',
                            'created_by' => null,
                        ]);
                        $slabsSeeded++;
                    }
                }
            }

            $this->line("Tenant #{$tenant->id} ({$tenant->company_name}, state={$state}): processed");
        }

        $this->info("Rate configs seeded: {$rateConfigsSeeded}, PT slab rows seeded: {$slabsSeeded}");

        return self::SUCCESS;
    }

    private function fixCeilingMetadata(?string $tenantOpt): void
    {
        $fixed = 0;

        foreach (self::CEILING_FIXES as $code => $fields) {
            $templateFixed = PayrollComponentTemplate::where('code', $code)->update([
                'has_wage_ceiling' => true,
                'ceiling_amount' => $fields['ceiling_amount'],
                'ceiling_apply_rule' => $fields['ceiling_apply_rule'],
            ]);

            $masterFixed = PayrollComponentMaster::withoutGlobalScope('tenant')
                ->where('code', $code)
                ->when($tenantOpt, fn ($q) => $q->where('tenant_id', $tenantOpt))
                ->update([
                    'has_wage_ceiling' => true,
                    'ceiling_amount' => $fields['ceiling_amount'],
                    'ceiling_apply_rule' => $fields['ceiling_apply_rule'],
                ]);

            $fixed += $templateFixed + $masterFixed;
        }

        $this->line("Ceiling metadata corrected on {$fixed} template/catalog rows (pf_employee/pf_employer/esi_employee/esi_employer).");

        $prorationFixed = 0;
        foreach (self::PRORATION_FIXES as $code => $rule) {
            $prorationFixed += PayrollComponentTemplate::where('code', $code)->update(['proration_rule' => $rule]);
            $prorationFixed += PayrollComponentMaster::withoutGlobalScope('tenant')
                ->where('code', $code)
                ->when($tenantOpt, fn ($q) => $q->where('tenant_id', $tenantOpt))
                ->update(['proration_rule' => $rule]);
        }

        $this->line("Proration rule corrected on {$prorationFixed} template/catalog rows (pt -> no_proration).");
    }
}
