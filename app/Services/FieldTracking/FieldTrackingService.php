<?php

namespace App\Services\FieldTracking;

use App\Events\AttendanceDomainEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserJobDetail;
use Illuminate\Support\Facades\DB;

/**
 * Single home for field-tracking seat rules — shared by the web employee list
 * (User\UserController) and the mobile API (Api\Attendance\AttendanceController)
 * so the entitlement logic cannot drift.
 */
class FieldTrackingService
{
    /** @var array<int,Tenant|null> */
    private array $tenantCache = [];

    /** Active employees currently consuming a field-tracking seat. */
    public function seatsUsed(int $tenantId): int
    {
        return (int) DB::table('user_job_details as jd')
            ->join('users as u', 'u.id', '=', 'jd.user_id')
            ->where('u.tenant_id', $tenantId)
            ->where('u.status', 1)
            ->where('jd.location_tracking_enabled', 1)
            ->count();
    }

    public function seatsPurchased(Tenant $tenant): int
    {
        return (int) ($tenant->field_tracking_seats ?? 0);
    }

    /** Cadence to hand the app: tenant value clamped to the config floor/ceiling. */
    public function pingSeconds(Tenant $tenant): int
    {
        $raw = (int) ($tenant->field_tracking_ping_seconds ?? 0);
        if ($raw <= 0) {
            $raw = (int) config('location.default_ping_seconds', 60);
        }

        return max(
            (int) config('location.min_ping_seconds', 30),
            min((int) config('location.max_ping_seconds', 900), $raw)
        );
    }

    /**
     * The `location_tracking` block returned by GET /today and used by /track.
     *
     * @return array{enabled:bool, ping_seconds:int, mode:string, batch_max:int}
     */
    public function resolveForUser(int $userId): array
    {
        $row = DB::table('users as u')
            ->leftJoin('user_job_details as jd', 'jd.user_id', '=', 'u.id')
            ->where('u.id', $userId)
            ->first(['u.tenant_id', 'jd.location_tracking_enabled']);

        $tenant = $row ? $this->tenant((int) $row->tenant_id) : null;

        $enabled = (bool) ($tenant?->field_tracking_enabled)
            && (bool) ($row->location_tracking_enabled ?? false);

        return [
            'enabled' => $enabled,
            'ping_seconds' => $tenant ? $this->pingSeconds($tenant) : (int) config('location.default_ping_seconds', 60),
            'mode' => (string) config('location.mode', 'single'),
            'batch_max' => (int) config('location.batch_max', 60),
        ];
    }

    /**
     * @return array{ok:bool, reason:?string}
     */
    public function canEnable(int $tenantId): array
    {
        $tenant = $this->tenant($tenantId);

        if (! $tenant || ! $tenant->field_tracking_enabled) {
            return ['ok' => false, 'reason' => 'Field tracking is not enabled for your company.'];
        }

        $purchased = $this->seatsPurchased($tenant);
        if ($this->seatsUsed($tenantId) >= $purchased) {
            return [
                'ok' => false,
                'reason' => "All {$purchased} field-tracking seats are in use. Contact us to add more seats.",
            ];
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * @return array{success:bool, message:string, seats_used:int, seats_purchased:int}
     */
    public function assign(User $user): array
    {
        UserJobDetail::where('user_id', $user->id)->update(['location_tracking_enabled' => 1]);

        return $this->afterChange($user, 'field_tracking.seat_assigned', 'Field tracking enabled');
    }

    /**
     * @return array{success:bool, message:string, seats_used:int, seats_purchased:int}
     */
    public function remove(User $user): array
    {
        UserJobDetail::where('user_id', $user->id)->update(['location_tracking_enabled' => 0]);

        return $this->afterChange($user, 'field_tracking.seat_removed', 'Field tracking disabled');
    }

    private function afterChange(User $user, string $event, string $message): array
    {
        $tenantId = (int) $user->tenant_id;
        $tenant = $this->tenant($tenantId, true);
        $used = $this->seatsUsed($tenantId);
        $purchased = $tenant ? $this->seatsPurchased($tenant) : 0;

        try {
            event(new AttendanceDomainEvent($event, $tenantId, [
                'user_id' => $user->id,
                'seats_used' => $used,
                'seats_purchased' => $purchased,
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('field-tracking event failed: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => $message,
            'seats_used' => $used,
            'seats_purchased' => $purchased,
        ];
    }

    private function tenant(int $tenantId, bool $fresh = false): ?Tenant
    {
        if ($fresh || ! array_key_exists($tenantId, $this->tenantCache)) {
            $this->tenantCache[$tenantId] = Tenant::find($tenantId);
        }

        return $this->tenantCache[$tenantId];
    }
}
