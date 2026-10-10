<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMultiplePunchesSettingsRequest;
use App\Models\Tenant;
use App\Services\Attendance\PolicyResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Multiple-punches-per-day toggle (Company Policies page). Same shape as
 * NoticePeriodSettingsController/EmployeeIdSettingsController — a single
 * tenant-policy value. The index page lives on WorkforceSettingsController
 * (combined with Employee ID Prefix + Notice Period); this controller only
 * handles the update. See docs/modules.md "Attendance — multiple punches
 * per day" for how tenants.allow_multiple_punches is consumed.
 */
class MultiplePunchesSettingsController extends Controller
{
    public function update(UpdateMultiplePunchesSettingsRequest $request)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        DB::transaction(function () use ($request, $tenant) {
            $tenant->allow_multiple_punches = $request->boolean('allow_multiple_punches');
            if ($request->filled('auto_clockout_hours')) {
                $tenant->auto_clockout_hours = (int) $request->input('auto_clockout_hours');
            }
            $tenant->save();
        });

        app(PolicyResolver::class)->forget();

        return back()->with('success', 'Multiple punches setting updated.');
    }
}
