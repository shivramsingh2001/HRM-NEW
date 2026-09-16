<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayroll;
use App\Models\PayrollEmployeeStructure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payroll rebuild — Phase 8 cutover.
 *
 * The per-tenant switch between the legacy fixed-column engine and the new
 * dynamic engine, reusing the tenants.payroll_dynamic_ui_enabled column
 * added in Phase 3 (which already gates visibility of the new admin
 * screens) as the same flag that now also decides which engine
 * MonthlyPayrollController::store() actually runs. Flipping it never
 * touches existing data either way — legacy screens and historical
 * payslips keep working unchanged regardless of the current setting.
 */
class PayrollEngineSettingsController extends Controller
{
    public function index()
    {
        $tenant = app('current_tenant');

        $activeEmployees = User::where('status', 1)->count();
        $withDynamicStructure = PayrollEmployeeStructure::current()->count();
        $withLegacyPayroll = DB::table('user_payrolls')->where('is_current', 1)->count();

        $lastLegacyMonth = MonthlyPayroll::where('engine_version', 'legacy_fixed')->max('payroll_month');
        $lastDynamicMonth = MonthlyPayroll::where('engine_version', 'dynamic_v1')->max('payroll_month');

        return view('client.payroll.engine-settings.index', compact(
            'tenant', 'activeEmployees', 'withDynamicStructure', 'withLegacyPayroll',
            'lastLegacyMonth', 'lastDynamicMonth'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'enable_dynamic_engine' => 'required|boolean',
            'confirm' => 'required|accepted',
        ]);

        $tenantId = app('current_tenant')->id;
        $enable = $request->boolean('enable_dynamic_engine');

        DB::table('tenants')->where('id', $tenantId)->update(['payroll_dynamic_ui_enabled' => $enable]);

        return redirect()->route('payroll-engine-settings.index')
            ->with('success', $enable
                ? 'Dynamic payroll engine enabled. New "Process Payroll" runs will use it from now on.'
                : 'Reverted to the legacy payroll engine for new runs.');
    }
}
