<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFloatingWeekoffsRequest;
use App\Models\AttendancePolicy;
use App\Services\Attendance\PolicyResolver;
use Illuminate\Support\Facades\Auth;

/**
 * "Floating week-offs" card on the Company Policies page: N days off a month on
 * any day instead of fixed days. Saved like every other attendance_policies
 * card (today's row, all other fields carried forward); the nightly
 * weekoffs:allocate-floating applies it (FloatingWeekOffAllocator). Per
 * employee it can be changed on Employee 360 → Policies.
 */
class FloatingWeekoffsSettingsController extends Controller
{
    public function update(UpdateFloatingWeekoffsRequest $request, PolicyResolver $resolver)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $effectiveFrom = now()->format('Y-m-d');
        $current = $resolver->forTenantDate($tenantId, $effectiveFrom);

        AttendancePolicy::updateOrCreate(
            ['tenant_id' => $tenantId, 'effective_from' => $effectiveFrom],
            array_merge($current->toPersistableArray(), $request->validated(), ['created_by' => Auth::id()])
        );

        $resolver->forget();

        return back()->with('success', 'Floating week-offs updated. Missed days are turned into week-offs every night.');
    }
}
