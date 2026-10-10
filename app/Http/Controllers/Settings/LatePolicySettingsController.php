<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLatePolicySettingsRequest;
use App\Models\AttendancePolicy;
use App\Services\Attendance\PolicyResolver;
use Illuminate\Support\Facades\Auth;

/**
 * "Late Arrival" half of the Late/Early deduction card on the Company
 * Policies page (WorkforceSettingsController). Also owns the Grace Period
 * Source toggle (shared with Early Leaving — see workforce.blade.php).
 *
 * attendance_policies is a versioned "all columns together" row (see
 * DayClassificationSettingsController) — reads the currently-effective
 * policy first and only overwrites this card's own fields on top of it.
 */
class LatePolicySettingsController extends Controller
{
    public function update(UpdateLatePolicySettingsRequest $request, PolicyResolver $resolver)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $data = $request->validated();

        // Applies from today (or the chosen "Apply from" day) — saved days are re-graded at once.
        $saved = app(\App\Services\Attendance\AttendancePolicyWriter::class)->save($tenantId, [
                'grace_mode' => $data['grace_mode'],
                'fixed_grace_minutes' => $data['fixed_grace_minutes'] ?? 0,
                'monthly_late_allowance' => $data['monthly_late_allowance'],
                'late_attendance_action' => $data['late_attendance_action'],
                // Keep the legacy boolean in sync so the big Attendance
                // Policy form's own checkbox still reflects reality.
                'late_halfday_enabled' => $data['late_attendance_action'] === 'half_day',
                'late_deduction_enabled' => $data['late_deduction_enabled'],
                'late_deduction_mode' => $data['late_deduction_mode'] ?? 'custom_multiplier',
                'late_deduction_amount' => $data['late_deduction_amount'] ?? null,
                'late_deduction_multiplier' => $data['late_deduction_multiplier'] ?? 1.00,
            ], $data['apply_from'] ?? null, Auth::id());

        return back()->with('success', 'Late arrival rules updated' . \App\Services\Attendance\AttendancePolicyWriter::summary($saved));
    }
}
