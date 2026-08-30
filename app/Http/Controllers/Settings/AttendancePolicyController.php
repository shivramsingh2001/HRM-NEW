<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAttendancePolicyRequest;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class AttendancePolicyController extends Controller
{
    /**
     * Show the attendance-policy settings page for the current tenant.
     */
    public function index()
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        return view('client.attendance.policy-settings', compact('tenant'));
    }

    /**
     * Persist the per-tenant late-arrival allowance.
     */
    public function update(UpdateAttendancePolicyRequest $request)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        $tenant->update([
            'late_halfday_enabled' => $request->boolean('late_halfday_enabled'),
            'monthly_late_allowance' => $request->input('monthly_late_allowance'),
        ]);

        return back()->with('success', 'Attendance policy updated. It applies from the next summary recalculation.');
    }
}
