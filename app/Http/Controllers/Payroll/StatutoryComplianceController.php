<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\StatutoryPtSlab;
use App\Models\StatutoryRateConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payroll rebuild — Phase 4.
 *
 * Admin UI for tenant-configurable statutory compliance: PF/ESI rates +
 * wage ceilings (statutory_rate_configs) and Professional Tax slabs
 * (statutory_pt_slabs). These are what PayrollCalculationEngine now reads
 * for any is_statutory component instead of a hardcoded rate, so editing
 * them here immediately changes every future payroll run that uses the
 * dynamic engine — TDS is deliberately absent (confirmed scope: stays
 * flat/manual on the component itself, no slab/regime engine).
 */
class StatutoryComplianceController extends Controller
{
    public function index()
    {
        $tenantId = app('current_tenant')->id;

        $rateConfigs = StatutoryRateConfig::whereIn('statutory_type', ['pf', 'esi'])
            ->orderByDesc('effective_from')
            ->get()
            ->groupBy('statutory_type')
            ->map(fn ($rows) => $rows->first()); // latest version per type

        $ptSlabs = StatutoryPtSlab::orderBy('state_code')->orderBy('gross_salary_min')->get()->groupBy('state_code');

        return view('client.payroll.compliance.index', compact('rateConfigs', 'ptSlabs'));
    }

    public function updateRate(Request $request, string $type)
    {
        if (! in_array($type, ['pf', 'esi'], true)) {
            abort(404);
        }

        $data = $request->validate([
            'employee_rate' => 'required|numeric|min:0|max:100',
            'employer_rate' => 'required|numeric|min:0|max:100',
            'wage_ceiling' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
        ]);

        $tenantId = app('current_tenant')->id;

        try {
            DB::beginTransaction();

            // Close out the previously-active row (if any) at the day
            // before this new version takes effect, then insert the new
            // version — same versioned-history pattern as
            // PayrollEmployeeStructure, so a rate change never rewrites
            // what was actually in force for past payroll runs.
            StatutoryRateConfig::where('statutory_type', $type)
                ->whereNull('effective_to')
                ->update(['effective_to' => \Carbon\Carbon::parse($data['effective_from'])->subDay()->toDateString()]);

            StatutoryRateConfig::create([
                'tenant_id' => $tenantId,
                'statutory_type' => $type,
                'region_code' => null,
                'effective_from' => $data['effective_from'],
                'effective_to' => null,
                'config' => [
                    'employee_rate' => $data['employee_rate'],
                    'employer_rate' => $data['employer_rate'],
                    'wage_ceiling' => $data['wage_ceiling'] ?? null,
                ],
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('payroll-compliance.index')
                ->with('success', strtoupper($type) . ' rates updated successfully.');
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to update rates: ' . $e->getMessage());
        }
    }

    public function storePtSlab(Request $request)
    {
        $data = $request->validate([
            'state_code' => 'required|string|max:60',
            'gross_salary_min' => 'required|numeric|min:0',
            'gross_salary_max' => 'nullable|numeric|min:0|gt:gross_salary_min',
            'pt_amount' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
        ]);

        StatutoryPtSlab::create(array_merge($data, [
            'tenant_id' => app('current_tenant')->id,
            'gender' => 'all',
            'created_by' => auth()->id(),
        ]));

        return redirect()->route('payroll-compliance.index')->with('success', 'PT slab added.');
    }

    public function destroyPtSlab($id)
    {
        StatutoryPtSlab::findOrFail($id)->delete();

        return redirect()->route('payroll-compliance.index')->with('success', 'PT slab removed.');
    }
}
