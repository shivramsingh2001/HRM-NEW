<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRequestLimitsSettingsRequest;
use App\Models\Tenant;
use App\Services\RequestLimitService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Company-wide WFH and regularization request limits (Company Policies page,
 * "Request limits" card) — tenants.wfh_* / regularization_* columns, 0 or empty
 * = no limit. Same shape as MultiplePunchesSettingsController; the page itself
 * is WorkforceSettingsController. Enforced by App\Services\RequestLimitService
 * on every web + mobile submit; one employee can get their own value on the
 * Employee 360 → Policies tab.
 */
class RequestLimitsSettingsController extends Controller
{
    public function update(UpdateRequestLimitsSettingsRequest $request)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        DB::transaction(function () use ($request, $tenant) {
            foreach (RequestLimitService::REQUEST_KEYS as $key) {
                $value = (int) $request->input($key, 0);
                $tenant->{$key} = $value > 0 ? $value : null;
            }
            $tenant->save();
        });

        return back()->with('success', 'Request limits updated.');
    }
}
