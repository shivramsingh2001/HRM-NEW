<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Attendance\PolicyResolver;
use App\Services\User\EmployeeIdService;
use Illuminate\Support\Facades\Auth;

/**
 * Combined "Company Policies" page: Employee ID Prefix + Notice Period +
 * Multiple Punches + Day Classification. Each is a single tenant-policy
 * value (or, for Day Classification, a slice of the versioned attendance
 * policy) previously spread across separate pages/sidebar links — merged
 * into one page here. Each card's form still posts to its own controller's
 * `update` route.
 */
class WorkforceSettingsController extends Controller
{
    public function index(PolicyResolver $resolver)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);
        $paddingLength = (int) config('employee_id.padding_length', 6);
        $currentPrefix = EmployeeIdService::prefixFor($tenant->id);
        $policy = $resolver->forTenantDate($tenant->id, now()->format('Y-m-d'));

        return view('client.settings.workforce', [
            'tenant' => $tenant,
            'policy' => $policy,
            'defaultDays' => (int) config('offboarding.default_notice_period_days'),
            'defaultPrefix' => strtoupper((string) config('employee_id.default_prefix', 'SH')),
            'paddingLength' => $paddingLength,
            'exampleId' => $currentPrefix . str_pad('123', $paddingLength, '0', STR_PAD_LEFT),
            // Overtime cards (shown when the overtime feature is on and the user may manage overtime).
            'overtime' => app(\App\Services\Attendance\OvertimePolicyService::class)->company($tenant->id),
            'canManageOvertime' => app(\App\Services\RbacService::class)->can(Auth::user(), 'overtime', 'manage'),
        ]);
    }
}
