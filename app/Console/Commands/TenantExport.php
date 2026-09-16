<?php

namespace App\Console\Commands;

use App\Support\TenantData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Tier 1 / W5b — export one tenant's data as newline-delimited JSON.
 *
 *   php artisan tenant:export 7 --to=storage/exports
 *
 * Writes <out>/tenant-<id>-<timestamp>/<table>.ndjson for every tenant-scoped
 * table plus a manifest.json, and logs the run to tenant_exports (which gates
 * `tenant:purge --hard`).
 */
class TenantExport extends Command
{
    protected $signature = 'tenant:export
                            {tenant : tenant id}
                            {--to=storage/app/tenant-exports : output directory}
                            {--chunk=2000 : rows per read}';

    protected $description = "Export a tenant's data to NDJSON files.";

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant');
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (! $tenant) {
            $this->error("Tenant {$tenantId} not found.");

            return self::FAILURE;
        }

        $chunk = max(100, (int) $this->option('chunk'));
        $stamp = now()->format('Ymd-His');
        $dir = rtrim($this->option('to'), '/\\') . "/tenant-{$tenantId}-{$stamp}";
        File::ensureDirectoryExists($dir);

        $counts = [];
        $tables = TenantData::tables();
        $this->info("Exporting tenant {$tenantId} ({$tenant->company_name}) — " . count($tables) . ' tables → ' . $dir);
        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        foreach ($tables as $table) {
            $path = "{$dir}/{$table}.ndjson";
            $handle = fopen($path, 'w');
            $n = 0;

            DB::table($table)->where('tenant_id', $tenantId)->orderBy('id')
                ->chunk($chunk, function ($rows) use ($handle, &$n) {
                    foreach ($rows as $row) {
                        fwrite($handle, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                        $n++;
                    }
                });

            fclose($handle);
            if ($n === 0) {
                @unlink($path);
            } else {
                $counts[$table] = $n;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $manifest = [
            'tenant_id' => $tenantId,
            'tenant_uuid' => $tenant->uuid ?? null,
            'company_name' => $tenant->company_name ?? null,
            'exported_at' => now()->toIso8601String(),
            'row_counts' => $counts,
            'total_rows' => array_sum($counts),
        ];
        File::put("{$dir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        DB::table('tenant_exports')->insert([
            'tenant_id' => $tenantId,
            'path' => $dir,
            'row_counts' => json_encode($counts),
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        $this->info('Done. ' . array_sum($counts) . ' rows across ' . count($counts) . " non-empty tables.\n{$dir}");

        return self::SUCCESS;
    }
}
