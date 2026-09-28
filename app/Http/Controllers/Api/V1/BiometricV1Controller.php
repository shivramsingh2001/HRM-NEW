<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Exceptions\DirectOnboardingBlockedException;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\User;
use App\Services\Biometric\BiometricEmployeeProvisioningService;
use App\Services\Biometric\BiometricPunchIngestService;
use App\Services\Biometric\BiometricRosterService;
use Illuminate\Http\Request;

/**
 * Ingest surface for the SBXPC Windows bridge. Auth = api_client key with
 * scope biometric:write (biometric:read for the enrollment map). current_tenant
 * is bound by the `apikey` middleware; every device/enrollment lookup is
 * re-scoped to it.
 */
class BiometricV1Controller extends Controller
{
    /** Batch punch ingest. Idempotent via the dedupe unique key. */
    public function punches(Request $request)
    {
        $max = (int) config('biometric.ingest_batch_max', 500);
        $data = $request->validate([
            'device_serial' => ['required', 'string'],
            'batch_ref' => ['nullable', 'string', 'max:120'],
            'punches' => ['required', 'array', 'min:1', "max:{$max}"],
            'punches.*.enroll_no' => ['required', 'string', 'max:64'],
            'punches.*.punched_at' => ['required', 'date'],
            'punches.*.raw_verify_mode' => ['nullable', 'integer'],
            'punches.*.method' => ['nullable', 'string', 'max:20'],
            'punches.*.direction' => ['nullable', 'in:in,out'],
            'punches.*.temperature' => ['nullable', 'numeric'],
            'punches.*.device_pos' => ['nullable', 'integer'],
        ]);

        $device = $this->device($data['device_serial']);

        return ApiResponse::ok(app(BiometricPunchIngestService::class)->ingest($device, $data['punches']));
    }

    /** Liveness ping from the bridge. */
    public function heartbeat(Request $request, string $serial)
    {
        $device = $this->device($serial);
        $device->forceFill([
            'last_seen_at' => now(),
            'model' => $request->input('model') ?: $device->model,
        ])->save();

        return ApiResponse::ok(['ok' => true]);
    }

    /** Bridge pushes the device's user list so the admin can map unknown IDs. */
    public function reportEnrollments(Request $request, string $serial)
    {
        $device = $this->device($serial);
        $data = $request->validate([
            'enrollments' => ['required', 'array'],
            'enrollments.*.enroll_no' => ['required', 'string', 'max:64'],
            'enrollments.*.name' => ['nullable', 'string', 'max:120'],
        ]);

        $added = 0;
        $linked = 0;
        foreach ($data['enrollments'] as $e) {
            $row = BiometricEnrollment::firstOrNew([
                'biometric_device_id' => $device->id,
                'enroll_no' => (string) $e['enroll_no'],
            ]);

            // A reported enroll_no that is a known tenant user id links itself —
            // no admin mapping needed for a device that already has users.
            $selfUserId = $this->userIdFromEnroll($device, (string) $e['enroll_no']);

            if (! $row->exists) {
                if (! $selfUserId && $device->allow_direct_onboarding) {
                    $row->tenant_id = $device->tenant_id;
                    $row->name_on_device = $e['name'] ?? null;
                    $row->source = 'manual';
                    $row->sync_state = 'synced';

                    try {
                        $provisioning = app(BiometricEmployeeProvisioningService::class);
                        $newUser = $provisioning->createFromDeviceEnrollment($device, (string) $e['enroll_no'], $e['name'] ?? null);
                        $row->user_id = $newUser->id;
                        $row->device_user_id = $newUser->id;
                        $row->save();

                        // Now that this row is linked, let the roster service
                        // pick the new employee up for any OTHER auto_provision
                        // devices in the tenant (this device is already covered).
                        app(BiometricRosterService::class)->syncUser($newUser);
                    } catch (DirectOnboardingBlockedException $ex) {
                        $row->last_error = $ex->getMessage();
                        $row->save();
                    }

                    $added++;

                    continue;
                }

                $row->tenant_id = $device->tenant_id;
                $row->name_on_device = $e['name'] ?? null;
                $row->user_id = $selfUserId;
                $row->device_user_id = $selfUserId;
                $row->source = $selfUserId ? 'auto' : 'manual';
                $row->sync_state = 'synced';
                $row->save();
                $selfUserId ? $linked++ : $added++;
            } else {
                $patch = [];
                if (empty($row->name_on_device) && ! empty($e['name'])) {
                    $patch['name_on_device'] = $e['name'];
                }
                if (! $row->user_id && $selfUserId) {
                    $patch += ['user_id' => $selfUserId, 'device_user_id' => $selfUserId, 'source' => 'auto'];
                    $linked++;
                }
                if ($patch) {
                    $row->update($patch);
                }
            }
        }

        return ApiResponse::ok([
            'added' => $added,
            'linked' => $linked,
            'total' => $device->enrollments()->count(),
        ]);
    }

    /**
     * Desired-state feed for the bridge's provisioner (scope biometric:read).
     * Rows the roster service flagged pending (create/update) or removing (delete).
     */
    public function roster(Request $request, string $serial)
    {
        $device = $this->device($serial);
        $priv = (int) ($device->default_privilege ?? 0);

        $rows = BiometricEnrollment::where('biometric_device_id', $device->id)
            ->whereIn('sync_state', ['pending', 'removing'])
            ->whereNotNull('device_user_id')
            ->get();

        $roster = app(BiometricRosterService::class);
        $users = User::withoutGlobalScope('tenant')
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique())
            ->get()->keyBy('id');

        $upserts = [];
        $deletes = [];
        foreach ($rows as $r) {
            if ($r->sync_state === 'removing') {
                $deletes[] = (int) $r->device_user_id;

                continue;
            }
            $u = $users->get($r->user_id);
            $upserts[] = [
                'id' => (int) $r->device_user_id,
                'name' => $u ? $roster->nameFor($u) : ($r->name_pushed ?: $r->name_on_device ?: (string) $r->device_user_id),
                'privilege' => $priv,
                'card' => $u?->card_number ?: null,
            ];
        }

        return ApiResponse::ok(['upserts' => $upserts, 'deletes' => $deletes]);
    }

    /** The bridge reports what it applied on the device. */
    public function rosterAck(Request $request, string $serial)
    {
        $device = $this->device($serial);
        $data = $request->validate([
            'applied' => ['required', 'array'],
            'applied.*.id' => ['required', 'integer'],
            'applied.*.action' => ['required', 'in:upsert,delete'],
            'applied.*.ok' => ['required', 'boolean'],
            'applied.*.error' => ['nullable', 'string', 'max:300'],
        ]);

        $roster = app(BiometricRosterService::class);
        $synced = 0;
        $removed = 0;
        $failed = 0;

        foreach ($data['applied'] as $a) {
            $row = BiometricEnrollment::where('biometric_device_id', $device->id)
                ->where('device_user_id', $a['id'])->first();
            if (! $row) {
                continue;
            }

            if (! $a['ok']) {
                $row->update(['sync_state' => 'failed', 'last_error' => $a['error'] ?? 'device rejected']);
                $failed++;

                continue;
            }

            if ($a['action'] === 'delete') {
                $row->delete();
                $removed++;

                continue;
            }

            $user = $row->user_id
                ? User::withoutGlobalScope('tenant')->find($row->user_id)
                : null;

            $row->update([
                'sync_state' => 'synced',
                'synced_at' => now(),
                'name_pushed' => $user ? $roster->nameFor($user) : $row->name_pushed,
                'card_pushed' => $user ? ($user->card_number ?: null) : $row->card_pushed,
                'last_error' => null,
            ]);
            $synced++;
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        return ApiResponse::ok(['synced' => $synced, 'removed' => $removed, 'failed' => $failed]);
    }

    /** enroll_no -> {user_id, employee_id} map (scope biometric:read). */
    public function enrollments(Request $request, string $serial)
    {
        $device = $this->device($serial);

        $rows = BiometricEnrollment::where('biometric_device_id', $device->id)
            ->with('user:id,employee_id')
            ->get()
            ->map(fn ($e) => [
                'enroll_no' => $e->enroll_no,
                'user_id' => $e->user_id,
                'employee_id' => $e->user?->employee_id,
                'name_on_device' => $e->name_on_device,
            ]);

        return ApiResponse::ok($rows);
    }

    private function userIdFromEnroll(BiometricDevice $device, string $enrollNo): ?int
    {
        return app(BiometricPunchIngestService::class)->userIdFromEnroll($device, $enrollNo);
    }

    private function device(string $serial): BiometricDevice
    {
        $device = BiometricDevice::where('serial_number', $serial)
            ->where('tenant_id', (int) app('current_tenant')->id)
            ->first();

        if (! $device) {
            throw new ApiException('device.unknown', "No biometric device with serial '{$serial}' in your tenant.", 404);
        }
        if (! $device->is_active) {
            throw new ApiException('device.inactive', "Device '{$serial}' is disabled.", 409);
        }

        return $device;
    }
}
