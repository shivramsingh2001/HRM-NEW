<?php

namespace App\Console\Commands;

use App\Models\BiometricPunch;
use Illuminate\Console\Command;

/**
 * Deletes settled biometric_punches (processed / skipped) past the retention
 * window. The attendances they produced are never touched. Schedule daily.
 */
class BiometricPrune extends Command
{
    protected $signature = 'biometric:prune {--days= : override config retention} {--dry-run}';

    protected $description = 'Prune old settled biometric punch records.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('biometric.punch_retention_days', 180));
        $cutoff = now()->subDays($days);

        $q = BiometricPunch::whereIn('status', ['processed', 'skipped'])
            ->where('created_at', '<', $cutoff);

        $count = (clone $q)->count();
        $this->info("{$count} punch(es) older than {$days}d" . ($this->option('dry-run') ? ' [dry-run]' : ''));

        if (! $this->option('dry-run') && $count > 0) {
            $deleted = 0;
            do {
                $n = $q->limit(5000)->delete();
                $deleted += $n;
            } while ($n > 0);
            $this->info("Deleted {$deleted}.");
        }

        return self::SUCCESS;
    }
}
