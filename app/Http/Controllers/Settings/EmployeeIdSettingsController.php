<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmployeeIdSettingsRequest;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-configurable employee ID prefix (Basic Setup). Same single-value
 * shape as NoticePeriodSettingsController — only affects employee IDs
 * generated from here on; existing employees keep their current IDs.
 * The index page for this lives on WorkforceSettingsController (combined
 * with Notice Period); this controller now only handles the update.
 */
class EmployeeIdSettingsController extends Controller
{
    public function update(UpdateEmployeeIdSettingsRequest $request)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        DB::transaction(function () use ($request, $tenant) {
            $tenant->employee_id_prefix = strtoupper($request->string('employee_id_prefix'));
            $tenant->save();
        });

        return back()->with('success', 'Employee ID prefix updated. It will apply to employees added from now on.');
    }
}
