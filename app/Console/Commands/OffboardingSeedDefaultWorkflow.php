<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Offboarding\OffboardingService;
use Illuminate\Console\Command;

/**
 * Backfills a default offboarding/offboarding_termination ApprovalService
 * workflow for every existing tenant. Idempotent — safe to re-run. New
 * tenants self-heal the same way the first time OffboardingService::submit()
 * runs for them, so this command only matters for tenants that existed
 * before this module's rebuild.
 */
class OffboardingSeedDefaultWorkflow extends Command
{
    protected $signature = 'offboarding:seed-default-workflow {--tenant= : Only seed this tenant ID}';

    protected $description = 'Seed the default Offboarding approval workflow (Manager->HR / HR-only for termination) for tenants that don\'t have one yet.';

    public function handle(OffboardingService $service): int
    {
        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::pluck('id')->all();

        foreach ($tenantIds as $tenantId) {
            $service->ensureDefaultWorkflow($tenantId, 'offboarding');
            $service->ensureDefaultWorkflow($tenantId, 'offboarding_termination');
        }

        $this->info('Seeded default offboarding workflows for ' . count($tenantIds) . ' tenant(s).');

        return self::SUCCESS;
    }
}
