<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEarlyLeavingPolicySettingsRequest;
use App\Models\AttendancePolicy;
use App\Services\Attendance\PolicyResolver;
use Illuminate\Support\Facades\Auth;

/**
 * "Early Leaving" half of the Late/Early deduction card on the Company
 * Policies page. The Grace Period Source toggle is owned by the Late
 * Arrival card (LatePolicySettingsController) — this controller carries
 * grace_mode/fixed_grace_minutes forward unchanged, it never edits them.
 */
class EarlyLeavingPolicySettingsController extends Controller
{
    public function update(UpdateEarlyLeavingPolicySettingsRequest $request, PolicyResolver $resolver)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $data = $request->validated();

        // Applies from today (or the chosen "Apply from" day) — saved days are re-graded at once.
        $saved = app(\App\Services\Attendance\AttendancePolicyWriter::class)->save($tenantId, [
                'monthly_early_allowance' => $data['monthly_early_allowance'],
                'early_attendance_action' => $data['early_attendance_action'],
                'early_deduction_enabled' => $data['early_deduction_enabled'],
                'early_deduction_mode' => $data['early_deduction_mode'] ?? 'custom_multiplier',
                'early_deduction_amount' => $data['early_deduction_amount'] ?? null,
                'early_deduction_multiplier' => $data['early_deduction_multiplier'] ?? 1.00,
            ], $data['apply_from'] ?? null, Auth::id());

        return back()->with('success', 'Early leaving rules updated' . \App\Services\Attendance\AttendancePolicyWriter::summary($saved));
    }
}
