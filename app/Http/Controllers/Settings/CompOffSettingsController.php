<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompOffSettingsRequest;
use App\Models\AttendancePolicy;
use App\Services\Attendance\PolicyResolver;
use App\Services\Leave\CompOffService;
use Illuminate\Support\Facades\Auth;

/**
 * "Comp-off" card on the Company Policies page: working a week-off or holiday
 * earns a day off later (CompOffService). Saved like every other
 * attendance_policies card; per employee on Employee 360 → Policies.
 */
class CompOffSettingsController extends Controller
{
    public function update(UpdateCompOffSettingsRequest $request, PolicyResolver $resolver, CompOffService $compOff)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $effectiveFrom = now()->format('Y-m-d');
        $current = $resolver->forTenantDate($tenantId, $effectiveFrom);
        $data = $request->validated();

        AttendancePolicy::updateOrCreate(
            ['tenant_id' => $tenantId, 'effective_from' => $effectiveFrom],
            array_merge($current->toPersistableArray(), $data, ['created_by' => Auth::id()])
        );
        if ($data['comp_off_enabled']) {
            $compOff->leaveType($tenantId);   // so "Comp Off" shows in Apply Leave from now on
        }

        $resolver->forget();

        return back()->with('success', 'Comp-off rules updated.');
    }
}
