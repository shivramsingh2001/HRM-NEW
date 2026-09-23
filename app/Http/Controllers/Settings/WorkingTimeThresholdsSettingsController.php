<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkingTimeThresholdsRequest;
use App\Models\AttendancePolicy;
use App\Services\Attendance\PolicyResolver;
use Illuminate\Support\Facades\Auth;

/**
 * "Working-time thresholds (advisory)" card on the Company Policies page — a
 * standalone way to change just min_rest_hours/max_daily_hours/sandwich_leave
 * without going through the full Attendance Policy form. These fields are not
 * enforced anywhere yet (reserved for future compliance alerts) but are
 * carried forward like every other attendance_policies field so saving this
 * card never touches unrelated settings.
 */
class WorkingTimeThresholdsSettingsController extends Controller
{
    public function update(UpdateWorkingTimeThresholdsRequest $request, PolicyResolver $resolver)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $effectiveFrom = now()->format('Y-m-d');
        $current = $resolver->forTenantDate($tenantId, $effectiveFrom);
        $data = $request->validated();

        AttendancePolicy::updateOrCreate(
            ['tenant_id' => $tenantId, 'effective_from' => $effectiveFrom],
            array_merge($current->toPersistableArray(), [
                'min_rest_hours' => $data['min_rest_hours'] ?? null,
                'max_daily_hours' => $data['max_daily_hours'] ?? null,
                'sandwich_leave' => $data['sandwich_leave'],
                'created_by' => Auth::id(),
            ])
        );

        $resolver->forget();

        return back()->with('success', 'Working-time thresholds updated.');
    }
}
