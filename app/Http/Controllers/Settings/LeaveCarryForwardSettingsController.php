<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLeaveCarryForwardSettingsRequest;
use App\Models\Tenant;
use App\Services\LeaveYearService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Leave carry forward (Company Policies page) — tenants.leave_carry_forward_enabled
 * and the leave-year start. Same shape as MultiplePunchesSettingsController; the
 * page itself is WorkforceSettingsController. Applied by `leaves:carry-forward`
 * (App\Services\LeaveCarryForwardService) with each leave type's limit / expiry;
 * the leave-year start also drives the yearly credit.
 */
class LeaveCarryForwardSettingsController extends Controller
{
    public function update(UpdateLeaveCarryForwardSettingsRequest $request)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        DB::transaction(function () use ($request, $tenant) {
            $enabled = $request->boolean('leave_carry_forward_enabled');
            // Remember when it was switched on: a leave year that began earlier is never
            // carried forward retroactively.
            if ($enabled && ! $tenant->leave_carry_forward_enabled) {
                $tenant->leave_carry_forward_enabled_at = now();
            }
            $tenant->leave_carry_forward_enabled = $enabled;
            $tenant->leave_year_start_month = (int) $request->leave_year_start_month;
            $tenant->leave_year_start_day = (int) $request->leave_year_start_day;
            $tenant->save();
        });

        app(LeaveYearService::class)->forget();

        return back()->with('success', 'Leave carry forward settings updated.');
    }
}
