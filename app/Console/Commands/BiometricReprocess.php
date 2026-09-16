<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBiometricPunch;
use App\Models\BiometricPunch;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Re-dispatch ProcessBiometricPunch for punches still pending or errored — a
 * safety net for queue hiccups and for punches that were unmapped when they
 * arrived but have since been mapped. Schedule every 10 min.
 */
class BiometricReprocess extends Command
{
    protected $signature = 'biometric:reprocess
                            {--device= : restrict to one biometric_device_id}
                            {--status=pending,error : comma list of statuses to retry}
                            {--since= : only punches with punched_at on/after this date}
                            {--remap : re-resolve user_id for skipped-unmapped punches first}';

    protected $description = 'Retry unprocessed biometric punches.';

    public function handle(): int
    {
        $statuses = array_filter(array_map('trim', explode(',', (string) $this->option('status'))));

        if ($this->option('remap')) {
            $statuses[] = 'skipped';
        }

        $q = BiometricPunch::whereIn('status', $statuses)
            ->when($this->option('device'), fn ($x) => $x->where('biometric_device_id', $this->option('device')))
            ->when($this->option('since'), fn ($x) => $x->where('punched_at', '>=', Carbon::parse($this->option('since'))));

        $n = 0;
        $q->orderBy('id')->chunkById(500, function ($rows) use (&$n) {
            foreach ($rows as $row) {
                if ($this->option('remap') && ! $row->user_id) {
                    $userId = \App\Models\BiometricEnrollment::where('biometric_device_id', $row->biometric_device_id)
                        ->where('enroll_no', $row->enroll_no)->value('user_id');
                    if ($userId) {
                        $row->update(['user_id' => $userId, 'status' => 'pending', 'error' => null]);
                    } else {
                        continue;
                    }
                } elseif ($row->status !== 'pending') {
                    $row->update(['status' => 'pending', 'error' => null]);
                }

                ProcessBiometricPunch::dispatch($row->id);
                $n++;
            }
        });

        $this->info("Re-dispatched {$n} punch(es).");

        return self::SUCCESS;
    }
}
