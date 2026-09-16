<?php

namespace App\Console\Commands;

use App\Models\PayrollEmployeeStructure;
use App\Models\Tenant;
use App\Models\UserPayroll;
use App\Services\Payroll\PayrollStructureAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Payroll rebuild — Phase 9, Step 2.
 *
 * Finds every user whose legacy user_payrolls "current" row has no matching
 * current payroll_employee_structures row, and syncs one from it.
 *
 * This is NOT the same gap as the one-time Phase 1 backfill's dedup check
 * (which skips a user_payrolls row if a dynamic structure already exists
 * for that exact (user_id, effective_from) pair) — it exists because the
 * legacy system itself allowed multiple user_payrolls rows to share one
 * effective_from for the same user (repeated profile-edit saves before the
 * "same date -> update in place" check matched), so the backfill's own
 * dedup logic can find an OLDER duplicate-dated row already backfilled and
 * skip the newer one that's actually current — leaving that user's real,
 * current legacy salary invisible on the dynamic side.
 *
 * Idempotent: re-running only touches users still actually drifted.
 */
class PayrollResyncDriftedStructures extends Command
{
    protected $signature = 'payroll:resync-drifted-structures
                            {--tenant= : restrict to one tenant id}
                            {--dry-run : report what would happen, write nothing}';

    protected $description = 'Sync any user whose legacy current payroll has no matching current dynamic structure.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $tenantOpt = $this->option('tenant');

        $tenants = Tenant::when($tenantOpt, fn ($q) => $q->where('id', $tenantOpt))->get();
        $this->info("Tenants to check: {$tenants->count()}");

        $synced = 0;
        $skippedInactive = 0;

        DB::beginTransaction();

        try {
            foreach ($tenants as $tenant) {
                $legacyCurrent = UserPayroll::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->where('is_current', true)
                    ->get()
                    ->keyBy('user_id');

                $dynamicCurrentUserIds = PayrollEmployeeStructure::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->where('is_current', true)
                    ->pluck('user_id')
                    ->all();

                $driftedUserIds = array_diff($legacyCurrent->keys()->all(), $dynamicCurrentUserIds);

                if (empty($driftedUserIds)) {
                    continue;
                }

                $this->line("--- Tenant #{$tenant->id} ({$tenant->company_name}): {$this->countLabel(count($driftedUserIds))} drifted ---");

                $service = app(PayrollStructureAssignmentService::class);

                foreach ($driftedUserIds as $userId) {
                    $legacyRow = $legacyCurrent[$userId];

                    $isActiveEmployee = DB::table('users')->where('id', $userId)->value('status') == 1;

                    if (! $isActiveEmployee) {
                        $skippedInactive++;
                        $this->line("  user_id={$userId}: inactive employee, skipped.");

                        continue;
                    }

                    $flatValues = $legacyRow->only([
                        'basic_salary', 'hra', 'conveyence', 'medical_allowance', 'children_allowance',
                        'post_allowance', 'leave_travel_allowance', 'monthly_incentive', 'special_allowance',
                        'provident_fund', 'employer_provident_fund', 'esi', 'employer_esi', 'professional_tax', 'tds',
                    ]);

                    $components = $service->componentsFromFlatValues($tenant->id, $flatValues);

                    if (empty($components)) {
                        $this->warn("  user_id={$userId}: no components resolved from legacy values (all zero or catalog missing codes) — skipped.");

                        continue;
                    }

                    // The legacy system itself sometimes has multiple
                    // user_payrolls rows sharing one effective_from for the
                    // same user (repeated profile-edit saves before the
                    // "same date -> update in place" check matched) — the
                    // Phase 1 backfill may have already created a (now
                    // stale/superseded) dynamic row at this exact date from
                    // an earlier duplicate. Revive that row instead of
                    // inserting a second one and hitting the unique
                    // constraint on (tenant_id, user_id, effective_from).
                    $existing = $service->findForExactDate($tenant->id, $userId, $legacyRow->effective_from);

                    if ($dry) {
                        $action = $existing ? 'would revive stale structure #' . $existing->id : 'would sync a new corrective structure';
                        $this->line("  user_id={$userId}: {$action} (ctc={$legacyRow->ctc}, effective_from={$legacyRow->effective_from}).");
                        $synced++;

                        continue;
                    }

                    if ($existing) {
                        $service->activateExistingAsCurrent($existing, $components, (float) $legacyRow->ctc);
                    } else {
                        $service->assign($tenant->id, $components, [
                            'user_id' => $userId,
                            'ctc' => (float) $legacyRow->ctc,
                            'effective_from' => $legacyRow->effective_from,
                            'revision_type' => 'correction',
                            'revision_reason' => 'Phase 9 drift resync — legacy row #' . $legacyRow->id,
                            'created_by' => null,
                            'source' => 'manual',
                        ]);
                    }

                    $synced++;
                    $this->line("  user_id={$userId}: synced.");
                }
            }

            $this->info('=== Summary ===');
            $this->info(($dry ? 'Would sync' : 'Synced') . ": {$synced}");
            $this->info("Skipped (inactive employee): {$skippedInactive}");

            if ($dry) {
                DB::rollBack();
                $this->warn('DRY RUN — nothing was written, transaction rolled back.');
            } else {
                DB::commit();
                $this->info('Committed.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Resync failed, rolled back: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function countLabel(int $count): string
    {
        return $count === 1 ? '1 user' : "{$count} users";
    }
}
