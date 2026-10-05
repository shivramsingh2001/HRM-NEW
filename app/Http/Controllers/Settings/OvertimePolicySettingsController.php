<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOvertimePolicySettingsRequest;
use App\Models\OvertimeSetting;
use App\Services\Attendance\AutoOvertimeService;
use App\Services\Attendance\OvertimePolicyService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

/**
 * Company Policies → "Overtime" + "Overtime limits & rate" cards (replaces the
 * old Overtime → Settings page). One overtime_settings row per company; read
 * everywhere through App\Services\Attendance\OvertimePolicyService.
 */
class OvertimePolicySettingsController extends Controller
{
    public function update(UpdateOvertimePolicySettingsRequest $request, OvertimePolicyService $policy)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $row = OvertimeSetting::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenantId]);
        if (! $row->exists) {
            $row->fill(OvertimePolicyService::DEFAULTS);
        }
        $old = $row->exists ? $row->only($row->getFillable()) : [];
        $wasAuto = $row->exists && $row->enabled && $row->mode === OvertimeSetting::MODE_AUTO;

        if ($request->input('section') === 'limits') {
            $num = fn ($k) => $request->filled($k) && (float) $request->input($k) > 0 ? round((float) $request->input($k), 2) : null;
            $row->fill([
                'min_hours' => $num('min_hours'),
                'max_hours_per_day' => $num('max_hours_per_day'),
                'max_hours_per_month' => $num('max_hours_per_month'),
                'rate_type' => $request->input('rate_type'),
                'rate_multiplier' => $request->filled('rate_multiplier') ? $request->input('rate_multiplier') : ($row->rate_multiplier ?? 1.5),
                'fixed_rate_per_hour' => $request->input('rate_type') === 'fixed' ? $num('fixed_rate_per_hour') : $row->fixed_rate_per_hour,
                'require_approval' => $request->boolean('require_approval'),
                'auto_approve_limit' => $num('auto_approve_limit'),
            ]);
        } else {
            $row->fill([
                'enabled' => $request->boolean('enabled'),
                'mode' => $request->input('mode'),
                'auto_start_basis' => $request->input('auto_start_basis') ?: 'grace',
                'auto_start_after_minutes' => (int) $request->input('auto_start_after_minutes', 0),
            ]);
        }
        $row->save();
        $policy->forget();

        app(AuditLogger::class)->record('user', Auth::id(), $tenantId, 'overtime_settings.updated', 'OvertimeSetting', (int) $row->id,
            $old, $row->only($row->getFillable()));

        $message = 'Overtime settings updated.';
        // Switched to automatic (or the start rule changed): this month so far is calculated now.
        if ($row->enabled && $row->mode === OvertimeSetting::MODE_AUTO && $request->input('section') === 'mode') {
            $n = app(AutoOvertimeService::class)->syncRange($tenantId, now()->startOfMonth(), now());
            $message .= $wasAuto ? " Recalculated this month: {$n} day(s) with overtime." : " Automatic overtime is on — {$n} day(s) this month already have overtime.";
        }

        return back()->with('success', $message);
    }
}
