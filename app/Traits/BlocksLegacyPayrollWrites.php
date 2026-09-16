<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Payroll rebuild — Phase 8 cutover.
 *
 * Once a tenant has switched to the dynamic engine (PayrollEngineSettingsController),
 * the legacy "Payroll Master" / "Employee Payroll" screens go read-only for
 * new writes — new hires and revisions belong on the dynamic Payroll
 * Structures / Employee Payroll Structures screens instead, so the two
 * systems never drift out of sync for the same employee. Existing legacy
 * records are never touched; this only blocks NEW create/update actions,
 * and reverts automatically if the tenant switches back to the legacy
 * engine.
 */
trait BlocksLegacyPayrollWrites
{
    private function blockedByDynamicCutover(): ?\Illuminate\Http\RedirectResponse
    {
        if (! app()->bound('current_tenant')) {
            return null;
        }

        $tenantId = app('current_tenant')->id;
        $dynamicEnabled = (bool) DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled');

        if (! $dynamicEnabled) {
            return null;
        }

        return redirect()->back()->with(
            'error',
            'This company has switched to the dynamic payroll engine. Please use Payroll Structures / '
                . 'Employee Payroll Structures instead of this legacy screen.'
        );
    }
}
