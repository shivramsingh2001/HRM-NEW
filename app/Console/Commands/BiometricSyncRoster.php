<?php

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use App\Services\Biometric\BiometricRosterService;
use Illuminate\Console\Command;

/**
 * Reconcile every auto_provision terminal's user list against HRM. Safety net
 * for anything the User observer missed. Schedule every ~15 min. The bridge
 * applies the resulting pending/removing rows on its next poll cycle.
 */
class BiometricSyncRoster extends Command
{
    protected $signature = 'biometric:sync-roster
                            {--device= : restrict to one biometric_device_id}';

    protected $description = 'Recompute the desired user roster for biometric terminals.';

    public function handle(BiometricRosterService $roster): int
    {
        $devices = BiometricDevice::where('auto_provision', true)
            ->when($this->option('device'), fn ($q) => $q->where('id', $this->option('device')))
            ->get();

        $pending = 0;
        $removing = 0;
        $conflicts = 0;
        foreach ($devices as $device) {
            $r = $roster->rebuild($device);
            $pending += $r['pending'];
            $removing += $r['removing'];
            $conflicts += $r['conflicts'];
            $this->line("[{$device->serial_number}] targets {$r['targets']}, +{$r['pending']} pending, -{$r['removing']} removing"
                . ($r['conflicts'] ? ", {$r['conflicts']} manual conflict(s) skipped" : ''));
        }

        $this->info("Done. {$devices->count()} device(s): {$pending} to push, {$removing} to remove"
            . ($conflicts ? ", {$conflicts} manual conflict(s)." : '.'));

        return self::SUCCESS;
    }
}
