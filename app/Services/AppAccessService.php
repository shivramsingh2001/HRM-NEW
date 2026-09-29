<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * What the mobile app may show a user: the company's plan features (modules
 * bought) and the user's role permissions. Returned by GET /api/user/features
 * and inside the login response, so the app can hide menus/buttons up front.
 * The same feature keys are enforced server-side by the `feature:` middleware
 * on routes/api.php, so hiding is cosmetic and bypassing it gets a 403.
 */
class AppAccessService
{
    public function __construct(private FeatureService $features, private RbacService $rbac)
    {
    }

    public function forUser(User $user): array
    {
        $tenantId = (int) $user->tenant_id;
        $features = $tenantId ? $this->features->allForTenant($tenantId) : [];

        return [
            'role' => $user->role,
            'plan' => $tenantId ? $this->plan($tenantId) : null,
            'features' => $features,
            'enabled_features' => array_keys(array_filter($features)),
            'location_tracking' => $this->locationTracking($user, (bool) ($features['geo_tracking'] ?? false)),
            'permissions' => $this->rbac->effectiveMatrix($user),
        ];
    }

    /**
     * Two levels, so the app knows both what the company bought and whether THIS
     * employee must send location pings:
     *  - company_enabled:  plan includes geo_tracking AND the company's field-tracking switch is on
     *  - employee_enabled: company_enabled AND this employee holds a tracking seat
     *    (user_job_details.location_tracking_enabled) — same rule /user/attendance/track applies.
     */
    private function locationTracking(User $user, bool $planIncludes): array
    {
        $tenantSwitch = (bool) DB::table('tenants')->where('id', $user->tenant_id)->value('field_tracking_enabled');
        $lt = app(\App\Services\FieldTracking\FieldTrackingService::class)->resolveForUser((int) $user->id);
        $company = $planIncludes && $tenantSwitch;

        return [
            'company_enabled' => $company,
            'employee_enabled' => $company && $lt['enabled'],
            'ping_seconds' => $lt['ping_seconds'],
            'mode' => $lt['mode'],
            'batch_max' => $lt['batch_max'],
        ];
    }

    private function plan(int $tenantId): ?array
    {
        $sub = DB::table('tenant_subscriptions as s')
            ->leftJoin('subscription_plans as p', 'p.id', '=', 's.plan_id')
            ->where('s.tenant_id', $tenantId)
            ->whereIn('s.status', ['active', 'trial'])
            ->where(fn ($q) => $q->whereNull('s.end_date')->orWhereDate('s.end_date', '>=', now()->toDateString()))
            ->orderByDesc('s.start_date')->orderByDesc('s.id')
            ->first(['p.name', 'p.slug', 's.status', 's.start_date', 's.end_date', 's.trial_ends_at']);

        return $sub ? [
            'name' => $sub->name,
            'slug' => $sub->slug,
            'status' => $sub->status,
            'start_date' => $sub->start_date,
            'end_date' => $sub->end_date,
            'trial_ends_at' => $sub->trial_ends_at,
        ] : null;
    }
}
