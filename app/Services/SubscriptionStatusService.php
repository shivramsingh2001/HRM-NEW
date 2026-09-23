<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves whether the tenant's subscription is healthy, ending soon, or
 * already past its end date — for the in-app warning banner
 * (resources/views/client/layout/subscription-banner.blade.php). Reads the
 * same shared tenant_subscriptions table as FeatureService::resolve().
 */
class SubscriptionStatusService
{
    /** @return array{state: string, end: Carbon, days: int}|null null = healthy / open-ended, nothing to show */
    public function forTenant(int $tenantId): ?array
    {
        $sub = DB::table('tenant_subscriptions')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->orderByDesc('start_date')->orderByDesc('id')
            ->first();
        if (! $sub) {
            return null;
        }

        $endsAt = $sub->end_date ?? ($sub->status === 'trial' ? $sub->trial_ends_at : null);
        if (! $endsAt) {
            return null; // open-ended plan
        }

        $end = Carbon::parse($endsAt);
        $daysLeft = (int) round(now()->startOfDay()->diffInDays($end->copy()->startOfDay(), false));

        if ($daysLeft < 0) {
            return ['state' => 'expired', 'end' => $end, 'days' => abs($daysLeft)];
        }
        if ($daysLeft <= (int) config('subscription.warning_days', 7)) {
            return ['state' => 'warning', 'end' => $end, 'days' => $daysLeft];
        }

        return null;
    }
}
