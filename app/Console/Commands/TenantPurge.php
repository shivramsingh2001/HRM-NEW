<?php

namespace App\Console\Commands;

use App\Support\TenantData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tier 1 / W5c — offboard a tenant.
 *
 *   php artisan tenant:purge 7 --dry-run
 *   php artisan tenant:purge 7 --confirm=<tenant-uuid>            (soft: archive)
 *   php artisan tenant:purge 7 --confirm=<tenant-uuid> --hard     (delete all data)
 *
 * --hard requires a tenant_exports row from the last 7 days (or --force) and
 * always writes a tenant_purges tombstone that is never itself deleted.
 */
class TenantPurge extends Command
{
    protected $signature = 'tenant:purge
                            {tenant : tenant id}
                            {--confirm= : the tenant uuid, required for any real change}
                            {--hard : permanently delete all rows (default is a soft archive)}
                            {--reason= : recorded on the tombstone}
                            {--force : skip the "recent export" requirement for --hard}
                            {--dry-run : print row counts and exit}';

    protected $description = 'Archive (soft) or permanently delete (hard) a tenant and all its data.';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant');
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (! $tenant) {
            $this->error("Tenant {$tenantId} not found.");

            return self::FAILURE;
        }

        $tables = TenantData::tables();
        $counts = [];
        foreach ($tables as $t) {
            $c = DB::table($t)->where('tenant_id', $tenantId)->count();
            if ($c > 0) {
                $counts[$t] = $c;
            }
        }
        $total = array_sum($counts);

        $this->info("Tenant {$tenantId} ({$tenant->company_name}) — {$total} rows across " . count($counts) . ' tables.');
        $this->table(['Table', 'Rows'], collect($counts)->map(fn ($c, $t) => [$t, $c])->values());

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing changed.');

            return self::SUCCESS;
        }

        if ($this->option('confirm') !== ($tenant->uuid ?? null)) {
            $this->error('--confirm must equal the tenant uuid: ' . ($tenant->uuid ?? '(none)'));

            return self::FAILURE;
        }

        $mode = $this->option('hard') ? 'hard' : 'soft';

        if ($mode === 'soft') {
            DB::table('tenants')->where('id', $tenantId)->update([
                'status' => 'archived',
                'updated_at' => now(),
            ]);
            $this->tombstone($tenantId, $tenant->uuid ?? null, 'soft', $counts);
            $this->info('Tenant archived. Data retained; sign-in disabled by status.');

            return self::SUCCESS;
        }

        // hard
        $recentExport = DB::table('tenant_exports')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();
        if (! $recentExport && ! $this->option('force')) {
            $this->error('No tenant_exports row in the last 7 days. Run `tenant:export` first, or pass --force.');

            return self::FAILURE;
        }

        if (! $this->confirm("PERMANENTLY delete {$total} rows for tenant {$tenantId}? This cannot be undone.")) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($tables, $tenantId) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $t) {
                DB::table($t)->where('tenant_id', $tenantId)->delete();
            }
            DB::table('tenants')->where('id', $tenantId)->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        });

        $this->tombstone($tenantId, $tenant->uuid ?? null, 'hard', $counts);
        $this->info("Hard purge complete. {$total} rows removed; tombstone written to tenant_purges.");

        return self::SUCCESS;
    }

    private function tombstone(int $tenantId, ?string $uuid, string $mode, array $counts): void
    {
        DB::table('tenant_purges')->insert([
            'tenant_id' => $tenantId,
            'tenant_uuid' => $uuid,
            'mode' => $mode,
            'counts' => json_encode($counts),
            'actor_id' => Auth::id(),
            'reason' => $this->option('reason'),
            'created_at' => now(),
        ]);
    }
}
