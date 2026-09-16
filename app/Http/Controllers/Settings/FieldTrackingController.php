<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFieldTrackingRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FieldTracking\FieldTrackingService;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant-facing field-tracking settings.
 *
 * The seat count and master switch are set by the vendor (external billing) and
 * shown read-only here. The tenant admin can adjust cadence + retention only.
 */
class FieldTrackingController extends Controller
{
    public function index(FieldTrackingService $service)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        $enabledUsers = User::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereHas('jobDetails', fn ($q) => $q->where('location_tracking_enabled', 1))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        return view('client.settings.field-tracking', [
            'tenant' => $tenant,
            'seatsUsed' => $service->seatsUsed($tenantId),
            'enabledUsers' => $enabledUsers,
        ]);
    }

    public function update(UpdateFieldTrackingRequest $request)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        // Never writes field_tracking_seats or field_tracking_enabled (vendor-owned).
        $tenant->update([
            'field_tracking_ping_seconds' => (int) $request->input('field_tracking_ping_seconds'),
            'field_tracking_retention_days' => max(
                (int) config('location.retention_floor_days', 30),
                (int) $request->input('field_tracking_retention_days')
            ),
        ]);

        return back()->with('success', 'Field tracking settings saved.');
    }
}
