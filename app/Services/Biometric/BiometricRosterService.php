<?php

namespace App\Services\Biometric;

use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\User;
use App\Models\UserJobDetail;

/**
 * Keeps a terminal's user list in step with HRM. The bridge reads the resulting
 * pending/removing rows (GET /roster), applies them on the device, and acks
 * (POST /roster/ack). No enroll number is ever mapped by hand.
 *
 * Key rule: the device user id == users.id, stored on the enrollment row as
 * device_user_id, with enroll_no = (string) users.id so punch ingest resolves
 * straight back to the employee.
 */
class BiometricRosterService
{
    /**
     * Recompute the desired user set for one device and flag the diff.
     *
     * @return array{pending:int,removing:int,targets:int}
     */
    public function rebuild(BiometricDevice $device): array
    {
        $tenantId = (int) $device->tenant_id;
        $targets = $this->targetUsers($device);          // [user_id => User]
        $pending = 0;
        $removing = 0;

        $conflicts = 0;
        foreach ($targets as $user) {
            // A direct-onboarded (or otherwise manually mapped) row already
            // represents this person on this device under a different
            // enroll_no — don't create a second, biometrics-less identity.
            if ($this->alreadyOnDeviceUnderOtherEnroll($device, $user)) {
                continue;
            }

            // A deliberate manual mapping of this enroll_no to a different person
            // wins — don't hijack it.
            $existing = BiometricEnrollment::where('biometric_device_id', $device->id)
                ->where('enroll_no', (string) $user->id)->first();
            if ($existing && $existing->source === 'manual' && $existing->user_id && (int) $existing->user_id !== (int) $user->id) {
                $conflicts++;

                continue;
            }

            if ($this->upsertEnrollment($device, $user)) {
                $pending++;
            }
        }

        // Auto rows that dropped out of scope (deactivated / off-boarded / branch move).
        $keepIds = array_keys($targets);
        $stale = BiometricEnrollment::where('biometric_device_id', $device->id)
            ->where('source', 'auto')
            ->when($keepIds, fn ($q) => $q->whereNotIn('user_id', $keepIds))
            ->where('sync_state', '!=', 'removing')
            ->get();

        foreach ($stale as $row) {
            $row->update(['sync_state' => 'removing']);
            $removing++;
        }

        return [
            'pending' => $pending,
            'removing' => $removing,
            'targets' => count($targets),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Cheap single-employee path for the model observer. Touches every
     * auto_provision device in the employee's tenant.
     */
    public function syncUser(User $user): void
    {
        $devices = BiometricDevice::where('tenant_id', $user->tenant_id)
            ->where('auto_provision', true)
            ->get();

        foreach ($devices as $device) {
            if ($this->alreadyOnDeviceUnderOtherEnroll($device, $user)) {
                continue;
            }

            $inScope = $this->userInScope($device, $user);

            $existing = BiometricEnrollment::where('biometric_device_id', $device->id)
                ->where('enroll_no', (string) $user->id)->first();

            // Never touch a manual mapping of this enroll_no to someone else.
            if ($existing && $existing->source === 'manual' && $existing->user_id && (int) $existing->user_id !== (int) $user->id) {
                continue;
            }

            if (! $inScope) {
                if ($existing && $existing->source === 'auto' && $existing->sync_state !== 'removing') {
                    $existing->update(['sync_state' => 'removing']);
                }

                continue;
            }

            $this->upsertEnrollment($device, $user);
        }
    }

    /**
     * Immediately mark specific users pending for a specific device,
     * regardless of the device's auto_provision flag — used by the
     * Employees list's "Push to device" bulk action, where the admin picks
     * both the employees and the target device explicitly.
     *
     * @param  iterable<User>  $users
     * @return array{queued:int,skipped:int}
     */
    public function pushToDevice(BiometricDevice $device, iterable $users): array
    {
        $queued = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if ((int) $user->tenant_id !== (int) $device->tenant_id) {
                $skipped++;

                continue;
            }

            if ($this->alreadyOnDeviceUnderOtherEnroll($device, $user)) {
                $skipped++;

                continue;
            }

            $existing = BiometricEnrollment::where('biometric_device_id', $device->id)
                ->where('enroll_no', (string) $user->id)->first();
            if ($existing && $existing->source === 'manual' && $existing->user_id && (int) $existing->user_id !== (int) $user->id) {
                $skipped++;

                continue;
            }

            if ($this->upsertEnrollment($device, $user)) {
                $queued++;
            } else {
                $skipped++;
            }
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /** Name string written to the terminal: "Ravi Kumar (EMP17)", clipped. */
    public function nameFor(User $user): string
    {
        $name = trim((string) $user->name);
        if (config('biometric.roster.name_include_employee_id', true) && $user->employee_id) {
            $name = trim($name . ' (' . $user->employee_id . ')');
        }

        $max = (int) config('biometric.roster.name_max_len', 40);

        return $max > 0 ? mb_substr($name, 0, $max) : $name;
    }

    /**
     * Shared upsert body for rebuild()/syncUser()/pushToDevice(): find-or-create
     * the (device, enroll_no=user id) row, flag it pending if it's new or the
     * pushed name/card would change. Returns whether it was flagged pending.
     */
    private function upsertEnrollment(BiometricDevice $device, User $user): bool
    {
        $row = BiometricEnrollment::firstOrNew([
            'biometric_device_id' => $device->id,
            'enroll_no' => (string) $user->id,
        ]);

        $desiredName = $this->nameFor($user);
        $desiredCard = $user->card_number ?: null;
        $isNew = ! $row->exists;

        $row->tenant_id = (int) $device->tenant_id;
        $row->user_id = $user->id;
        $row->device_user_id = $user->id;
        $row->source = 'auto';
        $row->name_on_device = $row->name_on_device ?: $desiredName;

        $flagPending = $isNew
            || $row->name_pushed !== $desiredName
            || $row->card_pushed !== $desiredCard
            || $row->sync_state === 'failed';

        if ($flagPending) {
            $row->sync_state = 'pending';
        }

        $row->save();

        return $flagPending;
    }

    /**
     * True when this user already has an enrollment row on this device under
     * a different enroll_no than (string) $user->id — e.g. a direct-onboarded
     * employee, whose row deliberately keeps the device's original enroll_no
     * (see BiometricEmployeeProvisioningService). Prevents rebuild()/
     * syncUser()/pushToDevice() from creating a second, biometrics-less
     * identity for the same person.
     */
    private function alreadyOnDeviceUnderOtherEnroll(BiometricDevice $device, User $user): bool
    {
        return BiometricEnrollment::where('biometric_device_id', $device->id)
            ->where('user_id', $user->id)
            ->where('enroll_no', '!=', (string) $user->id)
            ->exists();
    }

    /** @return array<int,User> keyed by user id */
    private function targetUsers(BiometricDevice $device): array
    {
        $q = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $device->tenant_id)
            ->where('status', 1);

        if ($device->provision_scope === 'branch' && $device->branch_id) {
            $branchUserIds = UserJobDetail::where('tenant_id', $device->tenant_id)
                ->where('office_branch', $device->branch_id)
                ->pluck('user_id');
            $q->whereIn('id', $branchUserIds);
        }

        return $q->get(['id', 'name', 'employee_id', 'card_number', 'tenant_id'])->keyBy('id')->all();
    }

    private function userInScope(BiometricDevice $device, User $user): bool
    {
        if ((int) $user->tenant_id !== (int) $device->tenant_id || (int) $user->status !== 1) {
            return false;
        }

        if ($device->provision_scope === 'branch' && $device->branch_id) {
            return UserJobDetail::where('user_id', $user->id)
                ->where('office_branch', $device->branch_id)
                ->exists();
        }

        return true;
    }
}
