<?php

namespace App\Services\Payroll;

use App\Models\PayrollComponentMaster;
use App\Models\PayrollEmployeeStructure;
use App\Models\PayrollRevisionLog;
use App\Services\Approvals\ApprovalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Payroll rebuild — Phase 9.
 *
 * Shared "create/revise a dynamic payroll structure" logic, extracted so any
 * legacy write path (UserController's new-hire wizard / employee-profile
 * payroll step) can create a real PayrollEmployeeStructure +
 * PayrollEmployeeComponent snapshot instead of a legacy user_payrolls row
 * once a tenant is on the dynamic engine — reusing the same
 * approval/revision-log/arrears wiring PayrollEmployeeStructureController
 * already established, rather than duplicating it.
 */
class PayrollStructureAssignmentService
{
    /**
     * Legacy flat field name -> catalog component code. Mirrors
     * PayrollBackfillComponentCatalog::COMPONENT_TEMPLATES's column map so
     * the same legacy field always lands under the same catalog code either
     * way a tenant's data got there.
     */
    private const FLAT_FIELD_TO_CODE = [
        'basic_salary' => 'basic',
        'hra' => 'hra',
        'conveyence' => 'conveyance',
        'medical_allowance' => 'medical_allowance',
        'children_allowance' => 'children_allowance',
        'post_allowance' => 'post_allowance',
        'leave_travel_allowance' => 'leave_travel_allowance',
        'monthly_incentive' => 'monthly_incentive',
        'special_allowance' => 'special_allowance',
        'provident_fund' => 'pf_employee',
        'employer_provident_fund' => 'pf_employer',
        'esi' => 'esi_employee',
        'employer_esi' => 'esi_employer',
        'professional_tax' => 'pt',
        'tds' => 'tds',
    ];

    /**
     * Build a [componentMasterId => spec] map from the same flat field names
     * the legacy wizard/profile forms use. Skips zero-value fields and any
     * code not present in this tenant's own catalog (never guesses/creates a
     * component — every tenant's catalog already has these via the Phase 1
     * template clone).
     */
    public function componentsFromFlatValues(int $tenantId, array $values): array
    {
        $componentIds = PayrollComponentMaster::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'code');

        $components = [];

        foreach (self::FLAT_FIELD_TO_CODE as $field => $code) {
            $amount = (float) ($values[$field] ?? 0);

            if ($amount == 0.0 || ! isset($componentIds[$code])) {
                continue;
            }

            $components[$componentIds[$code]] = [
                'calculation_method' => 'fixed_amount',
                'calculation_base_type' => 'none',
                'amount' => $amount,
                'percentage_value' => null,
            ];
        }

        return $components;
    }

    /**
     * Find an existing structure for this exact effective date — mirrors
     * the legacy "same date resubmitted -> update in place" behavior in
     * UserController rather than creating a colliding/duplicate revision.
     */
    public function findForExactDate(int $tenantId, int $userId, string $effectiveFrom): ?PayrollEmployeeStructure
    {
        return PayrollEmployeeStructure::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('effective_from', $effectiveFrom)
            ->first();
    }

    /**
     * Update an existing structure's component snapshot in place (same
     * effective date resubmitted — no new revision/approval needed, exactly
     * like the legacy "same payroll_master_id + date" branch).
     */
    public function updateComponentsInPlace(PayrollEmployeeStructure $structure, array $components, float $ctc): void
    {
        DB::transaction(function () use ($structure, $components, $ctc) {
            $structure->components()->delete();

            foreach ($components as $componentId => $row) {
                $structure->components()->create([
                    'tenant_id' => $structure->tenant_id,
                    'payroll_component_master_id' => (int) $componentId,
                    'calculation_method' => $row['calculation_method'],
                    'calculation_base_type' => $row['calculation_base_type'] ?? 'none',
                    'amount' => $row['calculation_method'] === 'fixed_amount' ? ($row['amount'] ?? 0) : null,
                    'percentage_value' => $row['calculation_method'] === 'percentage' ? ($row['percentage_value'] ?? 0) : null,
                    'is_enabled' => true,
                ]);
            }

            $structure->update(['ctc' => $ctc]);
        });
    }

    /**
     * Revive an existing (typically stale/superseded) structure row as the
     * user's current one — e.g. a drift-resync landing on the exact
     * effective_from a duplicate legacy row was already once backfilled
     * from. Supersedes any other row currently marked current for this
     * user first, so "only one is_current per user" always holds.
     */
    public function activateExistingAsCurrent(PayrollEmployeeStructure $structure, array $components, float $ctc): void
    {
        DB::transaction(function () use ($structure, $components, $ctc) {
            PayrollEmployeeStructure::forUser($structure->user_id)
                ->current()
                ->where('id', '!=', $structure->id)
                ->get()
                ->each(fn ($other) => $other->update(['is_current' => false, 'status' => 'superseded']));

            $structure->components()->delete();

            foreach ($components as $componentId => $row) {
                $structure->components()->create([
                    'tenant_id' => $structure->tenant_id,
                    'payroll_component_master_id' => (int) $componentId,
                    'calculation_method' => $row['calculation_method'],
                    'calculation_base_type' => $row['calculation_base_type'] ?? 'none',
                    'amount' => $row['calculation_method'] === 'fixed_amount' ? ($row['amount'] ?? 0) : null,
                    'percentage_value' => $row['calculation_method'] === 'percentage' ? ($row['percentage_value'] ?? 0) : null,
                    'is_enabled' => true,
                ]);
            }

            $structure->update(['ctc' => $ctc, 'is_current' => true, 'status' => 'active']);
        });
    }

    /**
     * Create a new structure + component snapshot, opening the
     * payroll_revision approval workflow exactly like
     * PayrollEmployeeStructureController::store() does.
     *
     * @param array $components componentMasterId => ['calculation_method'=>, 'calculation_base_type'=>, 'amount'=>, 'percentage_value'=>]
     * @param array $meta user_id, ctc, effective_from, revision_type, revision_reason, created_by, source, payroll_structure_id(optional), actor(optional)
     */
    public function assign(int $tenantId, array $components, array $meta): PayrollEmployeeStructure
    {
        if (empty($components)) {
            throw new \RuntimeException('No payroll components resolved from the submitted values — nothing to assign.');
        }

        return DB::transaction(function () use ($tenantId, $components, $meta) {
            $previousStructure = PayrollEmployeeStructure::forUser($meta['user_id'])->current()->first();

            // Never created as immediately current — whether this revision
            // goes live now or waits for approval is decided right after,
            // once we know if the tenant has a payroll_revision workflow
            // configured (same contract as PayrollEmployeeStructureController).
            $structure = PayrollEmployeeStructure::create([
                'tenant_id' => $tenantId,
                'user_id' => $meta['user_id'],
                'payroll_structure_id' => $meta['payroll_structure_id'] ?? null,
                'effective_from' => $meta['effective_from'],
                'effective_to' => null,
                'is_current' => false,
                'ctc' => $meta['ctc'],
                'revision_type' => $meta['revision_type'],
                'revision_reason' => $meta['revision_reason'] ?? null,
                'status' => 'draft',
                'source' => $meta['source'] ?? 'manual',
                'created_by' => $meta['created_by'] ?? null,
            ]);

            foreach ($components as $componentId => $row) {
                $structure->components()->create([
                    'tenant_id' => $tenantId,
                    'payroll_component_master_id' => (int) $componentId,
                    'calculation_method' => $row['calculation_method'],
                    'calculation_base_type' => $row['calculation_base_type'] ?? 'none',
                    'amount' => $row['calculation_method'] === 'fixed_amount' ? ($row['amount'] ?? 0) : null,
                    'percentage_value' => $row['calculation_method'] === 'percentage' ? ($row['percentage_value'] ?? 0) : null,
                    'is_enabled' => true,
                ]);
            }

            $actor = $meta['actor'] ?? Auth::user();
            $approvalRequest = app(ApprovalService::class)->open('payroll_revision', $structure, $actor);

            $log = PayrollRevisionLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $meta['user_id'],
                'payroll_employee_structure_id' => $structure->id,
                'previous_payroll_employee_structure_id' => optional($previousStructure)->id,
                'revision_type' => $meta['revision_type'],
                'effective_from' => $meta['effective_from'],
                'reason' => $meta['revision_reason'] ?? null,
                'changed_by' => $meta['created_by'] ?? optional($actor)->id,
                'approved_by' => $approvalRequest ? null : ($meta['created_by'] ?? optional($actor)->id),
                'approval_request_id' => optional($approvalRequest)->id,
                'diff' => [
                    'previous_ctc' => optional($previousStructure)->ctc,
                    'new_ctc' => $meta['ctc'],
                ],
            ]);

            if ($approvalRequest) {
                $structure->update([
                    'status' => 'pending_approval',
                    'approval_request_id' => $approvalRequest->id,
                ]);
            } else {
                // No payroll_revision workflow configured for this tenant —
                // same immediate-effect behavior as the dedicated screen.
                $structure->update(['is_current' => true, 'status' => 'active']);

                app(PayrollArrearsCalculator::class)->computeForRevision($structure->fresh(), $log->id);
            }

            return $structure->fresh();
        });
    }

    /**
     * code -> legacy flat field name, the reverse of FLAT_FIELD_TO_CODE.
     * Used to present a dynamic structure's components in the same flat
     * shape older read-only consumers (AI\TeamController, AI\ProfileController,
     * AttendanceAnalyticsService) already expect from a UserPayroll row, so
     * those screens keep working for a tenant that has cut over instead of
     * silently showing stale/empty legacy data.
     */
    private const CODE_TO_FLAT_FIELD = [
        'basic' => 'basic_salary',
        'hra' => 'hra',
        'conveyance' => 'conveyence',
        'medical_allowance' => 'medical_allowance',
        'children_allowance' => 'children_allowance',
        'post_allowance' => 'post_allowance',
        'leave_travel_allowance' => 'leave_travel_allowance',
        'monthly_incentive' => 'monthly_incentive',
        'special_allowance' => 'special_allowance',
        'pf_employee' => 'provident_fund',
        'pf_employer' => 'employer_provident_fund',
        'esi_employee' => 'esi',
        'esi_employer' => 'employer_esi',
        'pt' => 'professional_tax',
        'tds' => 'tds',
    ];

    /**
     * Present a PayrollEmployeeStructure in the same flat shape a legacy
     * UserPayroll row has, for read-only consumers that haven't been
     * rebuilt around the dynamic catalog. Percentage-based components are
     * reported as 0 here (resolving them to a rupee amount requires running
     * the full PayrollCalculationEngine against a specific month) — this is
     * a best-effort snapshot for display, not an authoritative payroll
     * figure.
     */
    public function toLegacyShapedArray(PayrollEmployeeStructure $structure): array
    {
        $structure->loadMissing('components.component');

        $values = array_fill_keys(array_values(self::CODE_TO_FLAT_FIELD), 0.0);
        $grossEarnings = 0.0;
        $totalDeductions = 0.0;

        foreach ($structure->components as $row) {
            $component = $row->component;
            $amount = $row->calculation_method === 'fixed_amount' ? (float) ($row->amount ?? 0) : 0.0;

            if ($component && isset(self::CODE_TO_FLAT_FIELD[$component->code])) {
                $values[self::CODE_TO_FLAT_FIELD[$component->code]] = $amount;
            }

            if ($component?->component_type === 'earning') {
                $grossEarnings += $amount;
            } elseif ($component?->component_type === 'deduction') {
                $totalDeductions += $amount;
            }
        }

        return array_merge($values, [
            'payroll_code' => null,
            'effective_from' => optional($structure->effective_from)->format('Y-m-d'),
            'effective_to' => optional($structure->effective_to)->format('Y-m-d'),
            'is_current' => $structure->is_current,
            'gross_salary' => $grossEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $grossEarnings - $totalDeductions,
            'ctc' => (float) $structure->ctc,
        ]);
    }
}
