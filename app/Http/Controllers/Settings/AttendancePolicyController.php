<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAttendancePolicyRequest;
use App\Models\AttendancePolicy;
use App\Models\Tenant;
use App\Services\Attendance\PolicyResolver;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AttendancePolicyController extends Controller
{
    /**
     * Show the attendance-policy settings page for the current tenant.
     */
    public function index()
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        // The rules currently in force (tenant row, else global default, else config).
        $policy = app(PolicyResolver::class)->forTenantDate($tenantId, now()->format('Y-m-d'));

        // History for the "previous versions" list.
        $history = AttendancePolicy::where('tenant_id', $tenantId)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return view('client.attendance.policy-settings', compact('tenant', 'policy', 'history'));
    }

    /**
     * Persist a NEW versioned policy row (effective from the chosen date, default
     * today). The tenants.* late columns are dual-written for the transition
     * period so anything still reading them stays consistent.
     */
    public function update(UpdateAttendancePolicyRequest $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $data = $request->validated();

        $effectiveFrom = $request->filled('effective_from')
            ? Carbon::parse($request->input('effective_from'))->format('Y-m-d')
            : now()->format('Y-m-d');

        AttendancePolicy::updateOrCreate(
            ['tenant_id' => $tenantId, 'effective_from' => $effectiveFrom],
            array_merge(Arr::except($data, ['allow_multiple_punches']), [
                'created_by' => Auth::id(),
            ])
        );

        Tenant::whereKey($tenantId)->update([
            'late_halfday_enabled' => $data['late_halfday_enabled'],
            'monthly_late_allowance' => $data['monthly_late_allowance'],
            'allow_multiple_punches' => $data['allow_multiple_punches'],
        ]);

        app(PolicyResolver::class)->forget();

        return back()->with('success', "Attendance policy saved (effective {$effectiveFrom}). It applies from the next summary recalculation.");
    }
}
