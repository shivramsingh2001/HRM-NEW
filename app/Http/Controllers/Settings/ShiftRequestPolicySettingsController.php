<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ShiftRequestSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Company Policies → "Shift Requests" card: swaps / change requests on or
 * off, approval, notice, rest, monthly limit, colleague answer time,
 * same department / branch, roster-change notifications. One
 * shift_request_settings row per company, read through
 * ShiftRequestSetting::forTenant() (ShiftRequestService).
 */
class ShiftRequestPolicySettingsController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'min_notice_hours' => 'required|integer|min:0|max:720',
            'max_requests_per_month' => 'required|integer|min:0|max:100',
            'peer_response_hours' => 'required|integer|min:1|max:168',
        ]);

        $tenantId = (int) Auth::user()->tenant_id;
        $row = ShiftRequestSetting::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenantId]);
        if (! $row->exists) {
            $row->fill(ShiftRequestSetting::DEFAULTS);
        }
        $old = $row->exists ? $row->only(array_keys(ShiftRequestSetting::DEFAULTS)) : [];

        $row->fill($data + [
            'swap_enabled' => $request->boolean('swap_enabled'),
            'change_enabled' => $request->boolean('change_enabled'),
            'requires_approval' => $request->boolean('requires_approval'),
            'same_department_only' => $request->boolean('same_department_only'),
            'same_branch_only' => $request->boolean('same_branch_only'),
            'notify_on_roster_change' => $request->boolean('notify_on_roster_change'),
        ])->save();

        app(AuditLogger::class)->record('user', Auth::id(), $tenantId, 'shift_request_settings.updated', 'ShiftRequestSetting', (int) $row->id,
            $old, $row->only(array_keys(ShiftRequestSetting::DEFAULTS)));

        return back()->with('success', 'Shift request settings updated.');
    }
}
