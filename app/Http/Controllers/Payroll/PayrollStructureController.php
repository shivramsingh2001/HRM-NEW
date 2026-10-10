<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollComponentMaster;
use App\Models\PayrollStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Payroll rebuild — Phase 3.
 *
 * Admin CRUD for named, reusable payroll structures — an admin picks
 * components from the catalog (built via PayrollComponentController) and
 * bundles them into a template (e.g. "Standard Tech Employee"), which
 * PayrollEmployeeStructureController then assigns to individual employees.
 * Replaces the *role* of PayrollMaster for tenants on the new engine,
 * without touching PayrollMaster/payroll_masters at all.
 */
class PayrollStructureController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollStructure::query()->withCount('components');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', (bool) $request->status);
        }

        $structures = $query->orderBy('name')->get();

        // Rendered once into the shared Add/Edit drawer; the Edit drawer's
        // checkbox/override values are then populated over this same markup
        // via AJAX (see show()) rather than rendering a second checklist.
        // baseComponents eager-loaded so the "% Of (Earnings)" multi-select
        // per percentage component can pre-check the catalog's default.
        $components = PayrollComponentMaster::active()->with('baseComponents')->orderBy('priority')->orderBy('display_order')->get();

        return view('client.payroll.structures.index', compact('structures', 'components'));
    }

    /**
     * JSON data for the Edit drawer — which components are enabled for this
     * structure and their override values, so the shared drawer form (built
     * from the full active-component list) can be populated client-side.
     */
    public function show($id)
    {
        $structure = PayrollStructure::with('components.baseComponents')->findOrFail($id);

        $overrides = $structure->components->keyBy('payroll_component_master_id')->map(fn ($row) => [
            'override_calculation_method' => $row->override_calculation_method,
            'override_amount' => $row->override_amount,
            'override_percentage' => $row->override_percentage,
            'base_component_ids' => $row->baseComponents->pluck('id')->all(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $structure->id,
                'name' => $structure->name,
                'description' => $structure->description,
                'payroll_calculation_type' => $structure->payroll_calculation_type,
                'working_hours_per_day' => $structure->working_hours_per_day,
                'selected_component_ids' => $structure->components->pluck('payroll_component_master_id')->all(),
                'overrides' => $overrides,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = app('current_tenant')->id;
        $validator = $this->structureValidator($request, $tenantId);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $validated = $this->buildStructurePayload($validator->validated(), $request, $tenantId);

        if ($validated['components']->isEmpty()) {
            return response()->json([
                'success' => false,
                'errors' => ['components' => ['Select at least one component for this structure.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $structure = PayrollStructure::create(array_merge($validated['structure'], [
                'tenant_id' => $tenantId,
                'created_by' => auth()->id(),
            ]));

            $this->syncComponents($structure, $validated['components'], $tenantId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll structure created successfully.',
            ]);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create structure: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $structure = PayrollStructure::findOrFail($id);
        $tenantId = app('current_tenant')->id;
        $validator = $this->structureValidator($request, $tenantId, $structure->id);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $validated = $this->buildStructurePayload($validator->validated(), $request, $tenantId);

        if ($validated['components']->isEmpty()) {
            return response()->json([
                'success' => false,
                'errors' => ['components' => ['Select at least one component for this structure.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $structure->update($validated['structure']);
            $this->syncComponents($structure, $validated['components'], $tenantId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll structure updated successfully.',
            ]);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update structure: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $structure = PayrollStructure::findOrFail($id);

        // Employee assignments keep their own component snapshot regardless
        // (payroll_employee_structures.payroll_structure_id is nullable,
        // set null on delete) so removing a template is always safe.
        $structure->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payroll structure deleted.',
        ]);
    }

    private function structureValidator(Request $request, int $tenantId, ?int $ignoreId = null): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('payroll_structures', 'name')->where('tenant_id', $tenantId)->ignore($ignoreId),
            ],
            'code' => 'nullable|string|max:60',
            'description' => 'nullable|string',
            'payroll_calculation_type' => 'required|in:day_based,hour_based',
            'working_hours_per_day' => 'required_if:payroll_calculation_type,hour_based|nullable|numeric|min:1|max:24',
            'components' => 'required|array|min:1',
            'components.*.enabled' => 'nullable|boolean',
            'components.*.override_calculation_method' => 'nullable|in:fixed_amount,percentage',
            'components.*.override_amount' => 'nullable|numeric|min:0',
            'components.*.override_percentage' => 'nullable|numeric|min:0|max:100',
            // Per-structure override of which Earnings a percentage
            // component's base sums — empty/omitted falls back to the
            // catalog's own configured default (see syncComponents()).
            'components.*.base_component_ids' => 'nullable|array',
            'components.*.base_component_ids.*' => [
                'integer',
                Rule::exists('payroll_component_master', 'id')->where('tenant_id', $tenantId)->where('component_type', 'earning'),
            ],
        ]);
    }

    private function buildStructurePayload(array $data, Request $request, int $tenantId): array
    {
        // Defense-in-depth: don't trust the submitted array keys to already
        // belong to this tenant's own catalog.
        $ownComponentIds = PayrollComponentMaster::where('tenant_id', $tenantId)->pluck('id')->all();

        $selectedComponents = collect($request->input('components', []))
            ->filter(fn ($row) => ! empty($row['enabled']))
            ->filter(fn ($row, $componentId) => in_array((int) $componentId, $ownComponentIds, true))
            ->map(fn ($row, $componentId) => [
                'payroll_component_master_id' => (int) $componentId,
                'override_calculation_method' => $row['override_calculation_method'] ?? null,
                'override_amount' => $row['override_amount'] ?? null,
                'override_percentage' => $row['override_percentage'] ?? null,
                'base_component_ids' => array_map('intval', array_filter($row['base_component_ids'] ?? [])),
            ])
            ->values();

        return [
            'structure' => [
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'payroll_calculation_type' => $data['payroll_calculation_type'],
                'working_hours_per_day' => $data['payroll_calculation_type'] === 'hour_based'
                    ? ($data['working_hours_per_day'] ?? 8)
                    : null,
                'status' => true,
            ],
            'components' => $selectedComponents,
        ];
    }

    private function syncComponents(PayrollStructure $structure, $components, int $tenantId): void
    {
        $structure->components()->delete();

        $masters = PayrollComponentMaster::with('baseComponents')
            ->whereIn('id', collect($components)->pluck('payroll_component_master_id'))
            ->get()
            ->keyBy('id');

        foreach ($components as $row) {
            $master = $masters->get($row['payroll_component_master_id']);

            $structureComponent = $structure->components()->create([
                'tenant_id' => $tenantId,
                'payroll_component_master_id' => $row['payroll_component_master_id'],
                'override_calculation_method' => $row['override_calculation_method'],
                'override_amount' => $row['override_amount'],
                'override_percentage' => $row['override_percentage'],
                'is_mandatory' => true,
            ]);

            // "% Of (Earnings)" base selection for this template: the
            // submitted per-structure override takes precedence; falls
            // back to the catalog's own configured default when nothing
            // was submitted (row left at its pre-filled default) — same
            // precedence PayrollEmployeeStructureController's
            // createComponentSnapshots() already establishes one layer
            // down, at the per-employee level.
            $baseComponentIds = ! empty($row['base_component_ids'])
                ? $row['base_component_ids']
                : ($master && $master->calculation_base_type === 'component' ? $master->baseComponents->pluck('id')->all() : []);

            foreach ($baseComponentIds as $baseId) {
                $structureComponent->baseComponentBases()->create([
                    'tenant_id' => $tenantId,
                    'base_payroll_component_master_id' => $baseId,
                ]);
            }
        }
    }
}
