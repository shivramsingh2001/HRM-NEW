<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollComponentMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Payroll rebuild — Phase 3.
 *
 * Admin CRUD for the tenant's dynamic payroll component catalog
 * (payroll_component_master) — the "create Basic Salary, HRA, Conveyance,
 * Bonus, PF, ESI, Professional Tax..." screen the whole rebuild was
 * requested for. Additive: does not touch payroll_masters/PayrollMaster or
 * any existing screen.
 */
class PayrollComponentController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollComponentMaster::query()->with(['baseComponent', 'baseComponents']);

        if ($request->filled('component_type')) {
            $query->where('component_type', $request->component_type);
        }
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', (bool) $request->status);
        }

        $components = $query->orderBy('priority')->orderBy('display_order')->get();

        // Options for the "Calculate % Of -> Earnings components" multi-select
        // in both the Add and Edit modals. Percentage components (Deductions
        // like PF/ESIC/PT/TDS, and Employer Contributions) can only be
        // calculated against Earnings — scoped here so the option list can't
        // even offer a Deduction/Employer-Contribution base, in addition to
        // the server-side type check in assertValidBaseComponents(). Unlike
        // the old edit() page, this is one shared list rendered once for
        // every row's edit modal, so the component being edited can't be
        // excluded server-side any more — the edit-open JS instead disables
        // that one <option> client-side.
        $baseComponents = PayrollComponentMaster::active()->earnings()->orderBy('priority')->get();

        return view('client.payroll.components.index', compact('components', 'baseComponents'));
    }

    public function store(Request $request)
    {
        $tenantId = app('current_tenant')->id;

        $validator = $this->componentValidator($request, $tenantId);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $baseIds = array_map('intval', $validated['calculation_base_component_ids'] ?? []);

        if ($validated['calculation_base_type'] === 'component') {
            try {
                PayrollComponentMaster::assertValidBaseComponents($validated['component_type'], (int) $validated['priority'], $baseIds, $tenantId);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        $data = $this->prepareComponentData($validated, $request, $tenantId, $baseIds);

        try {
            DB::beginTransaction();

            $component = PayrollComponentMaster::create(array_merge($data, [
                'is_active' => true,
                'is_system_default' => false,
                'created_by' => auth()->id(),
            ]));

            $this->syncBaseComponents($component, $baseIds, $tenantId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll component created successfully.',
                'data' => $component,
            ]);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create component: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $component = PayrollComponentMaster::findOrFail($id);
        $tenantId = app('current_tenant')->id;

        if ($component->is_system_default && $request->code !== $component->code) {
            return response()->json([
                'success' => false,
                'message' => 'The code of a system-default component cannot be changed.',
            ], 422);
        }

        // "Basic Salary" is relied on by every tenant's CTC/salary calculation
        // as the universal percentage base — locked to its name and type so
        // it can never be renamed into something else or repurposed into a
        // Deduction/Employer Contribution out from under existing structures.
        if ($component->code === 'basic'
            && ($request->name !== $component->name || $request->component_type !== $component->component_type)) {
            return response()->json([
                'success' => false,
                'message' => 'Basic Salary is a fixed component — its name and type cannot be changed.',
            ], 422);
        }

        $validator = $this->componentValidator($request, $tenantId, $component->id);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $baseIds = array_map('intval', $validated['calculation_base_component_ids'] ?? []);

        if ($validated['calculation_base_type'] === 'component') {
            try {
                PayrollComponentMaster::assertValidBaseComponents($validated['component_type'], (int) $validated['priority'], $baseIds, $tenantId);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        $data = $this->prepareComponentData($validated, $request, $tenantId, $baseIds);

        try {
            DB::beginTransaction();

            $component->update(array_merge($data, ['updated_by' => auth()->id()]));

            $this->syncBaseComponents($component, $baseIds, $tenantId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll component updated successfully.',
                'data' => $component,
            ]);
        } catch (\RuntimeException $e) {
            // Thrown by PayrollComponentMaster's priority-ordering guard.
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update component: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|boolean']);

        try {
            $component = PayrollComponentMaster::findOrFail($id);

            // Every structure/slip assumes at least Basic Salary is always
            // available and active — deactivating it would silently zero out
            // every employee's CTC base. Kept fixed rather than allowing a
            // tenant to switch it off.
            if ($component->code === 'basic' && $component->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Basic Salary is a fixed component and cannot be deactivated.',
                ], 422);
            }

            $component->is_active = ! $component->is_active;
            $component->save();

            return response()->json([
                'success' => true,
                'status' => $component->is_active,
                'message' => 'Status updated successfully.',
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    private function componentValidator(Request $request, int $tenantId, ?int $ignoreId = null): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'code' => [
                'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('payroll_component_master', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'name' => 'required|string|max:255',
            'component_type' => 'required|in:earning,deduction,employer_contribution,reimbursement',
            'is_statutory' => 'nullable|boolean',
            'statutory_type' => 'nullable|in:pf,esi,pt,tds,lwf',
            'calculation_method' => 'required|in:fixed_amount,percentage',
            'calculation_base_type' => 'required|in:none,fixed_base,component',
            'calculation_base' => 'nullable|required_if:calculation_base_type,fixed_base|in:basic,gross_pass1,ctc',
            // Multi-select: "Calculate % Of -> Earnings components". Replaces
            // the old single calculation_base_component_id form field — that
            // column still exists on the model, but only as a denormalized
            // "primary base" mirror written from the first submitted id here
            // (see prepareComponentData()); the real, authoritative set is
            // the baseComponentSelections() pivot synced in store()/update().
            'calculation_base_component_ids' => 'nullable|array|required_if:calculation_base_type,component',
            'calculation_base_component_ids.*' => [
                'integer',
                Rule::exists('payroll_component_master', 'id')->where('tenant_id', $tenantId),
            ],
            'percentage_value' => 'nullable|required_if:calculation_method,percentage|numeric|min:0|max:100',
            'default_amount' => 'nullable|required_if:calculation_method,fixed_amount|numeric|min:0',
            'priority' => 'required|integer|min:1|max:999',
            'proration_rule' => 'required|in:prorate_by_payable_days,prorate_by_worked_hours,prorate_by_lop_days,no_proration',
            'is_taxable' => 'nullable|boolean',
            'has_wage_ceiling' => 'nullable|boolean',
            'ceiling_amount' => 'nullable|required_if:has_wage_ceiling,1|numeric|min:0',
            'ceiling_apply_rule' => 'nullable|required_if:has_wage_ceiling,1|in:cap_base_before_percentage,ceiling_exclude',
            'affects_gross' => 'nullable|boolean',
            'affects_ctc' => 'nullable|boolean',
            'affects_net' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:1|max:999',
        ]);
    }

    private function prepareComponentData(array $data, Request $request, int $tenantId, array $baseIds = []): array
    {
        foreach (['is_statutory', 'is_taxable', 'has_wage_ceiling', 'affects_gross', 'affects_ctc', 'affects_net'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['tenant_id'] = $tenantId;
        $data['display_order'] = $data['display_order'] ?? $data['priority'];

        // Not a real column — the multi-select submission is handled
        // separately via syncBaseComponents(), never passed to create()/update().
        unset($data['calculation_base_component_ids']);

        if ($data['calculation_base_type'] !== 'fixed_base') {
            $data['calculation_base'] = null;
        }

        // calculation_base_component_id is kept only as a denormalized
        // "primary base" mirror (the first submitted base) for any
        // backward-compat reader of that single column — the real,
        // authoritative multi-base set lives in baseComponentSelections().
        $data['calculation_base_component_id'] = $data['calculation_base_type'] === 'component'
            ? ($baseIds[0] ?? null)
            : null;

        if (! $data['has_wage_ceiling']) {
            $data['ceiling_amount'] = null;
            $data['ceiling_apply_rule'] = null;
        }

        return $data;
    }

    /**
     * Delete-then-recreate the component's base-selection pivot from the
     * submitted multi-select — mirrors this codebase's established pattern
     * for pivot-style tables (see PayrollStructureController::syncComponents()),
     * not belongsToMany()->sync() (which bypasses TenantTrait's tenant_id
     * auto-fill since it does raw inserts, not full Eloquent model saves).
     */
    private function syncBaseComponents(PayrollComponentMaster $component, array $baseIds, int $tenantId): void
    {
        $component->baseComponentSelections()->delete();

        if ($component->calculation_base_type !== 'component') {
            return;
        }

        foreach (array_unique($baseIds) as $baseId) {
            $component->baseComponentSelections()->create([
                'tenant_id' => $tenantId,
                'base_payroll_component_master_id' => $baseId,
            ]);
        }
    }
}
