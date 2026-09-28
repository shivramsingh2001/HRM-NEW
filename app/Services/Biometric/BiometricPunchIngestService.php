<?php

namespace App\Services\Biometric;

use App\Jobs\ProcessBiometricPunch;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricPunch;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Raw punch -> biometric_punches row (+ ProcessBiometricPunch dispatch). Shared
 * by the SBXPC bridge ingest (BiometricV1Controller::punches) and the FkWeb
 * direct-push receiver (BiometricFkWebController). Idempotent via the dedupe
 * key (serial_number, enroll_no, punched_at, raw_verify_mode).
 */
class BiometricPunchIngestService
{
    /**
     * @param  array<int,array{enroll_no:string,punched_at:string,raw_verify_mode?:int|null,method?:string|null,direction?:string|null,temperature?:float|null,device_pos?:int|null}>  $punches
     * @return array{accepted:int,duplicates:int,skipped_unmapped:int,unknown_enrolls:array<int,string>}
     */
    public function ingest(BiometricDevice $device, array $punches): array
    {
        $tenantId = (int) $device->tenant_id;

        // enroll_no -> user_id map for this device
        $map = BiometricEnrollment::where('biometric_device_id', $device->id)
            ->pluck('user_id', 'enroll_no')->all();

        $accepted = 0;
        $duplicates = 0;
        $skipped = 0;
        $unknown = [];

        foreach ($punches as $p) {
            $punchedAt = Carbon::parse($p['punched_at']);
            $rawVm = $p['raw_verify_mode'] ?? 0;
            $userId = $map[$p['enroll_no']]
                ?? $this->resolveEnroll($device, (string) $p['enroll_no'], $map);

            $existing = BiometricPunch::where('serial_number', $device->serial_number)
                ->where('enroll_no', $p['enroll_no'])
                ->where('punched_at', $punchedAt)
                ->where('raw_verify_mode', $rawVm)
                ->first();

            if ($existing) {
                $duplicates++;

                continue;
            }

            $row = BiometricPunch::create([
                'tenant_id' => $tenantId,
                'biometric_device_id' => $device->id,
                'serial_number' => $device->serial_number,
                'enroll_no' => $p['enroll_no'],
                'user_id' => $userId,
                'punched_at' => $punchedAt,
                'direction' => $p['direction'] ?? 'auto',
                'method' => $p['method'] ?? null,
                'raw_verify_mode' => $rawVm,
                'temperature' => $p['temperature'] ?? null,
                'device_pos' => $p['device_pos'] ?? null,
                'payload' => $p,
                'status' => $userId ? 'pending' : 'skipped',
                'error' => $userId ? null : 'unmapped enroll',
            ]);

            if ($userId) {
                $accepted++;
                ProcessBiometricPunch::dispatch($row->id);
            } else {
                $skipped++;
                if (! in_array($p['enroll_no'], $unknown, true)) {
                    $unknown[] = $p['enroll_no'];
                }
            }
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'last_punch_at' => now(),
        ])->save();

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'skipped_unmapped' => $skipped,
            'unknown_enrolls' => $unknown,
        ];
    }

    /** enroll_no -> users.id when it is a numeric id of an active tenant employee. */
    public function userIdFromEnroll(BiometricDevice $device, string $enrollNo): ?int
    {
        if (! ctype_digit($enrollNo)) {
            return null;
        }

        return User::withoutGlobalScope('tenant')
            ->where('tenant_id', $device->tenant_id)
            ->where('status', 1)
            ->where('id', (int) $enrollNo)
            ->value('id');
    }

    /**
     * Miss in the enroll map: if the enroll_no is a numeric id of an active
     * employee in this tenant, create the enrollment row on the fly and cache it
     * so the punch is processed instead of skipped as "unmapped".
     *
     * @param  array<string,int|null>  $map  mutated in place
     */
    private function resolveEnroll(BiometricDevice $device, string $enrollNo, array &$map): ?int
    {
        if (! config('biometric.roster.auto_resolve_punch_enrolls', true)) {
            return null;
        }

        $userId = $this->userIdFromEnroll($device, $enrollNo);
        if (! $userId) {
            return null;
        }

        BiometricEnrollment::updateOrCreate(
            ['biometric_device_id' => $device->id, 'enroll_no' => $enrollNo],
            [
                'tenant_id' => $device->tenant_id,
                'user_id' => $userId,
                'device_user_id' => $userId,
                'source' => 'auto',
                'sync_state' => 'synced',
                'synced_at' => now(),
            ],
        );

        $map[$enrollNo] = $userId;

        return $userId;
    }
}
