<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollComponentMaster;
use App\Models\PayrollEmployeeStructure;
use App\Models\PayrollRevisionLog;
use App\Models\PayrollStructure;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Payroll rebuild — Phase 3.
 *
 * Assigns a dynamic salary structure to an employee: pick a
 * PayrollStructure template (optional — components can also be picked
 * ad hoc), set CTC/effective date, adjust component values, submit. Creates
 * one PayrollEmployeeStructure + its PayrollEmployeeComponent snapshot rows.
 * This is the dynamic-engine equivalent of "Assign Payroll" (UserPayroll),
 * built alongside it without touching that screen or its data.
 *
 * Assign/Revise is a side drawer on the index page (not a separate page) —
 * forUser() supplies the AJAX prefill data for it.
 */
class PayrollEmployeeStructureController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->where('status', 1);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('employee_id', 'like', '%' . $request->search . '%');
            });
        }

        $employees = $query->orderBy('name')->paginate(15)->withQueryString();

        $currentStructures = PayrollEmployeeStructure::current()
            ->whereIn('user_id', $employees->pluck('id'))
            ->get()
            ->keyBy('user_id');

        // Rendered once into the shared Assign/Revise drawer.
        $structures = PayrollStructure::where('status', true)->with('components.component')->orderBy('name')->get();
        $components = PayrollComponentMaster::active()->orderBy('priority')->orderBy('display_order')->get();

        return view('client.payroll.employee-structures.index', compact(
            'employees', 'currentStructures', 'structures', 'components'
        ));
    }

    /**
     * JSON prefill for the Assign/Revise drawer: the employee's current
     * structure (if any) and its per-component overrides, so the shared
     * drawer form (built from the full active-component list) can be
     * populated client-side exactly like an edit.
     */
    public function forUser($userId)
    {
        $employee = User::findOrFail($userId);

        $current = PayrollEmployeeStructure::forUser($userId)->current()->with('components.component')->first();

        $overrides = $current
            ? $current->components->keyBy('payroll_component_master_id')->map(fn ($row) => [
                'calculation_method' => $row->calculation_method,
                'amount' => $row->amount,
                'percentage_value' => $row->percentage_value,
            ])
            : (object) [];

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_id' => $employee->employee_id,
                ],
                'current' => $current ? [
                    'ctc' => $current->ctc,
                    'effective_from' => optional($current->effective_from)->format('Y-m-d'),
                    'effective_from_display' => optional($current->effective_from)->format('d M Y'),
                ] : null,
                'selected_component_ids' => $current
                    ? $current->components->pluck('payroll_component_master_id')->all()
                    : [],
                'overrides' => $overrides,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = app('current_tenant')->id;

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'payroll_structure_id' => ['nullable', Rule::exists('payroll_structures', 'id')->where('tenant_id', $tenantId)],
            'ctc' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'revision_type' => 'required|in:initial,increment,promotion,demotion,correction,transfer',
            'revision_reason' => 'nullable|string|max:255',
            'components' => 'required|array|min:1',
            'components.*.enabled' => 'nullable|boolean',
            'components.*.calculation_method' => 'required_with:components|in:fixed_amount,percentage',
            'components.*.amount' => 'nullable|numeric|min:0',
            'components.*.percentage_value' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Defense-in-depth: the "components" array is keyed by component id
        // from a checkbox form field, which validate() above doesn't check
        // per-key — silently drop any id that isn't actually in this
        // tenant's own catalog rather than trust the submitted keys.
        $ownComponentIds = PayrollComponentMaster::where('tenant_id', $tenantId)->pluck('id')->all();

        $selected = collect($data['components'])
            ->filter(fn ($row) => ! empty($row['enabled']))
            ->filter(fn ($row, $componentId) => in_array((int) $componentId, $ownComponentIds, true));

        if ($selected->isEmpty()) {
            return response()->json([
                'success' => false,
                'errors' => ['components' => ['Enable at least one component for this employee.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $previousStructure = PayrollEmployeeStructure::forUser($data['user_id'])->current()->first();

            // Never created as immediately current — whether this revision
            // goes live now or waits for approval is decided right after,
            // once we know if the tenant has a payroll_revision workflow
            // configured.
            $structure = PayrollEmployeeStructure::create([
                'tenant_id' => $tenantId,
                'user_id' => $data['user_id'],
                'payroll_structure_id' => $data['payroll_structure_id'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to' => null,
                'is_current' => false,
                'ctc' => $data['ctc'],
                'revision_type' => $data['revision_type'],
                'revision_reason' => $data['revision_reason'] ?? null,
                'status' => 'draft',
                'source' => 'manual',
                'created_by' => auth()->id(),
            ]);

            foreach ($selected as $componentId => $row) {
                $structure->components()->create([
                    'tenant_id' => $tenantId,
                    'payroll_component_master_id' => (int) $componentId,
                    'calculation_method' => $row['calculation_method'],
                    'calculation_base_type' => 'none',
                    'amount' => $row['calculation_method'] === 'fixed_amount' ? ($row['amount'] ?? 0) : null,
                    'percentage_value' => $row['calculation_method'] === 'percentage' ? ($row['percentage_value'] ?? 0) : null,
                    'is_enabled' => true,
                ]);
            }

            $approvalRequest = app(ApprovalService::class)->open('payroll_revision', $structure, auth()->user());

            $log = PayrollRevisionLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $data['user_id'],
                'payroll_employee_structure_id' => $structure->id,
                'previous_payroll_employee_structure_id' => optional($previousStructure)->id,
                'revision_type' => $data['revision_type'],
                'effective_from' => $data['effective_from'],
                'reason' => $data['revision_reason'] ?? null,
                'changed_by' => auth()->id(),
                'approved_by' => $approvalRequest ? null : auth()->id(),
                'approval_request_id' => optional($approvalRequest)->id,
                'diff' => [
                    'previous_ctc' => optional($previousStructure)->ctc,
                    'new_ctc' => $data['ctc'],
                ],
            ]);

            if ($approvalRequest) {
                $structure->update([
                    'status' => 'pending_approval',
                    'approval_request_id' => $approvalRequest->id,
                ]);
                $message = 'Revision submitted for approval. It will take effect once approved.';
            } else {
                // No payroll_revision workflow configured for this tenant —
                // same immediate-effect behavior as before Phase 5.
                $structure->update(['is_current' => true, 'status' => 'active']);
                $message = 'Dynamic payroll structure assigned successfully.';

                $arrears = app(\App\Services\Payroll\PayrollArrearsCalculator::class)
                    ->computeForRevision($structure->fresh(), $log->id);
                if ($arrears->isNotEmpty()) {
                    $message .= ' ' . $arrears->count() . ' month(s) of arrears were queued from the backdated effective date.';
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign structure: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * JSON breakdown for the View drawer.
     */
    public function show($id)
    {
        $structure = PayrollEmployeeStructure::with(['user', 'components.component'])->findOrFail($id);

        $components = $structure->components
            ->filter(fn ($row) => $row->component)
            ->map(fn ($row) => [
                'name' => $row->component->name,
                'type' => $row->component->component_type,
                'calculation_method' => $row->calculation_method,
                'amount' => $row->amount,
                'percentage_value' => $row->percentage_value,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $structure->id,
                'user_id' => $structure->user_id,
                'employee' => [
                    'name' => $structure->user->name,
                    'employee_id' => $structure->user->employee_id,
                ],
                'ctc' => $structure->ctc,
                'revision_type' => ucfirst($structure->revision_type),
                'effective_from' => $structure->effective_from->format('d M Y'),
                'effective_to' => optional($structure->effective_to)->format('d M Y'),
                'is_current' => is_null($structure->effective_to),
                'components' => $components,
            ],
        ]);
    }
}
