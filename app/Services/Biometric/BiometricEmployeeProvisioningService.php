<?php

namespace App\Services\Biometric;

use App\Exceptions\DirectOnboardingBlockedException;
use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Observers\BiometricRosterObserver;
use App\Services\EmployeeCapService;
use App\Services\User\EmployeeIdService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Auto-creates an HRM employee from a device-side (walk-up) biometric
 * enrollment, for devices that opt in via biometric_devices.allow_direct_onboarding.
 * Called from BiometricV1Controller::reportEnrollments() (bridge) and
 * BiometricFkWebController::onboard() (direct push) when a reported enroll_no
 * matches no existing HRM user. Expects `current_tenant` to be bound.
 *
 * Deliberately smaller than EmployeeProvisioningService::hire() (the ATS
 * hire flow) — a device enrollment has none of the candidate/job-offer data
 * that flow uses, so only the minimum viable employee record is created
 * (User + one UserJobDetail row); HR fills in the rest later via the
 * existing Edit Employee drawer.
 */
class BiometricEmployeeProvisioningService
{
    public function __construct(private EmployeeCapService $caps)
    {
    }

    public function createFromDeviceEnrollment(BiometricDevice $device, string $enrollNo, ?string $deviceName): User
    {
        $tenantId = (int) $device->tenant_id;

        $cap = $this->caps->canCreateEmployee($tenantId);
        if (! $cap['ok']) {
            throw new DirectOnboardingBlockedException($cap['reason']);
        }

        $tenant = Tenant::find($tenantId);
        $displayName = trim((string) $deviceName) !== '' ? trim($deviceName) : "Device User {$enrollNo}";

        return DB::transaction(function () use ($tenant, $displayName) {
            // Suppressed: at this point the triggering BiometricEnrollment row
            // isn't linked to this user yet (the caller links it right after
            // this method returns) — letting the observer fire here would
            // create a second, redundant enrollment row for the same person.
            $user = BiometricRosterObserver::suppressed(function () use ($tenant, $displayName) {
                $placeholderEmail = 'pending-'.Str::uuid()->toString().'@placeholder.local';

                $user = User::create([
                    'name' => $displayName,
                    'email' => $placeholderEmail,
                    'password' => Hash::make('12345678'),
                    'status' => 1,
                    'role' => 'employee',
                    'must_change_password' => true,
                ]);

                $user->email = $this->finalEmail($tenant, $displayName, $user->id);
                $user->employee_id = EmployeeIdService::generate($user->tenant_id, $user->id);
                $user->save();

                UserJobDetail::create(['user_id' => $user->id]);

                return $user;
            });

            session()->flash('temp_password_notice',
                "Employee {$user->name} ({$user->employee_id}) auto-created from device enrollment. One-time password: 12345678 — ask them to change it after first login.");

            return $user;
        });
    }

    private function finalEmail(?Tenant $tenant, string $name, int $userId): string
    {
        $slug = Str::slug($name, '') ?: 'employee';
        $subdomain = $tenant?->subdomain ?: 'hrm';
        $base = "{$subdomain}.{$slug}{$userId}@gmail.com";

        if (! User::where('email', $base)->exists()) {
            return $base;
        }

        for ($i = 2; $i <= 5; $i++) {
            $candidate = "{$subdomain}.{$slug}{$userId}-{$i}@gmail.com";
            if (! User::where('email', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new DirectOnboardingBlockedException("Could not generate a unique email for '{$name}'.");
    }
}
