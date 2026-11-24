<?php

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use App\Services\Biometric\BiometricDayRebuilder;
use Illuminate\Console\Command;

/**
 * Re-apply a biometric device's current Direction rule to past days (same
 * service as Settings → Biometric → edit device → "Also apply to past days").
 *
 *   php artisan biometric:rebuild-days --device=3 --from=2026-11-01 --dry-run
 */
class BiometricRebuildDays extends Command
{
    protected $signature = 'biometric:rebuild-days
                            {--device= : biometric_devices.id (required)}
                            {--from= : first date, Y-m-d (default: 1st of this month)}
                            {--to= : last date, Y-m-d (default: today)}
                            {--dry-run : show what would change, write nothing}';

    protected $description = 'Rebuild past attendance days from machine punches using the device\'s current Direction rule.';

    public function handle(BiometricDayRebuilder $rebuilder): int
    {
        $device = BiometricDevice::find($this->option('device'));
        if (! $device) {
            $this->error('Device not found — pass --device=<biometric_devices.id>.');

            return self::FAILURE;
        }

        $s = $rebuilder->rebuild(
            $device,
            (string) ($this->option('from') ?: now()->startOfMonth()->toDateString()),
            (string) ($this->option('to') ?: now()->toDateString()),
            (bool) $this->option('dry-run'),
            'console',
        );

        $this->info("Device #{$device->id} {$device->name}");
        if ($s['changes']) {
            $this->table(['User', 'Date', 'Before in', 'Before out', 'After in', 'After out'], array_map(fn ($c) => [
                $c['user_id'], $c['date'], $c['before']['clock_in'], $c['before']['clock_out'], $c['after']['clock_in'], $c['after']['clock_out'],
            ], $s['changes']));
        }
        $this->info(BiometricDayRebuilder::message($s));

        return self::SUCCESS;
    }
}
