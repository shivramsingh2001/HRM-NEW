<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNoticePeriodSettingsRequest;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Company-wide Notice Period setting (Basic Setup). Same shape as
 * ShiftSettingsController — a single tenant-policy value read everywhere
 * offboarding computes a required notice period
 * (OffboardingService::requiredNoticeDays()).
 * The index page for this lives on WorkforceSettingsController (combined
 * with Employee ID Prefix); this controller now only handles the update.
 */
class NoticePeriodSettingsController extends Controller
{
    public function update(UpdateNoticePeriodSettingsRequest $request)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        DB::transaction(function () use ($request, $tenant) {
            $tenant->notice_period = $request->integer('notice_period');
            $tenant->save();
        });

        return back()->with('success', 'Notice period updated. It will apply as the default for new offboarding requests.');
    }
}
