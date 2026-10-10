<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDayClassificationSettingsRequest;
use App\Models\AttendancePolicy;
use Illuminate\Support\Facades\Auth;
use App\Services\Attendance\AttendanceRegradeService;
use App\Services\Attendance\PolicyResolver;
use Carbon\Carbon;

/**
 * "Day classification" card on the Company Policies page (WorkforceSettingsController) —
 * a standalone way to change just present_ratio/half_day_ratio/fallback_present_hours/
 * fallback_half_hours without going through the full Attendance Policy form. Since
 * attendance_policies is a versioned row (all columns together, keyed on
 * (tenant_id, effective_from) — see AttendancePolicyController), this reads the
 * currently-effective policy first and only overwrites the 4 day-classification
 * fields on top of it, so the other policy fields (overtime, grace, etc.) are
 * carried forward unchanged rather than nulled out.
 */
class DayClassificationSettingsController extends Controller
{
    public function update(UpdateDayClassificationSettingsRequest $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        // Off: only the switch is posted/kept; the thresholds carry forward.
        $data = $request->validated();
        $applyFrom = $data['apply_from'] ?? null;
        unset($data['apply_from']);

        // Saved days are re-graded at once (it used to wait for "the next
        // recalculation", so the Team page kept showing the old half days).
        $saved = app(\App\Services\Attendance\AttendancePolicyWriter::class)->save($tenantId, $data, $applyFrom, Auth::id());

        return back()->with('success', ($data['day_classification_enabled']
            ? 'Day classification turned on'
            : 'Day classification turned off — any day with work now counts as present')
            . \App\Services\Attendance\AttendancePolicyWriter::summary($saved));
    }
}
