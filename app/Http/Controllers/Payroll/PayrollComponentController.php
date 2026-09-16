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
        $query = PayrollComponentMaster::query()->with('baseComponent');

        if ($request->filled('component_type')) {
            $query->where('component_type', $request->component_type);
        }
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', (bool) $request->status);
        }

        $components = $query->orderBy('priority')->orderBy('display_order')->get();

        // Options for the "Calculate % Of -> Another component" select in
        // both the Add and Edit modals. Unlike the old edit() page, this is
        // one shared list rendered once for every row's edit modal, so the
        // component being edited can't be excluded server-side any more —
        // the edit-open JS instead disables that one <option> client-side.
        $baseComponents = PayrollComponentMaster::active()->orderBy('priority')->get();

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

        $data = $this->prepareComponentData($validator->validated(), $request, $tenantId);

        try {
            DB::beginTransaction();

            $component = PayrollComponentMaster::create(array_merge($data, [
                'is_active' => true,
                'is_system_default' => false,
                'created_by' => auth()->id(),
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll component created successfully.',
                'data' => $component,
            ]);
        } catch (\Exception $e) {
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

        $validator = $this->componentValidator($request, $tenantId, $component->id);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $this->prepareComponentData($validator->validated(), $request, $tenantId);

        try {
            DB::beginTransaction();

            $component->update(array_merge($data, ['updated_by' => auth()->id()]));

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
            $component->is_active = ! $component->is_active;
            $component->save();

            return response()->json([
                'success' => true,
                'status' => $component->is_active,
                'message' => 'Status updated successfully.',
            ]);
        } catch (\Exception $e) {
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
            'calculation_base_component_id' => [
                'nullable', 'required_if:calculation_base_type,component',
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

    private function prepareComponentData(array $data, Request $request, int $tenantId): array
    {
        foreach (['is_statutory', 'is_taxable', 'has_wage_ceiling', 'affects_gross', 'affects_ctc', 'affects_net'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['tenant_id'] = $tenantId;
        $data['display_order'] = $data['display_order'] ?? $data['priority'];

        if ($data['calculation_base_type'] !== 'fixed_base') {
            $data['calculation_base'] = null;
        }
        if ($data['calculation_base_type'] !== 'component') {
            $data['calculation_base_component_id'] = null;
        }
        if (! $data['has_wage_ceiling']) {
            $data['ceiling_amount'] = null;
            $data['ceiling_apply_rule'] = null;
        }

        return $data;
    }
}
