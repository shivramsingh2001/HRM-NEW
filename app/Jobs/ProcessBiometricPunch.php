<?php

namespace App\Jobs;

use App\Models\BiometricPunch;
use App\Services\Biometric\BiometricAttendanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Resolve one biometric punch into an attendances write. Unique per punch id;
 * the biometric_punches.status guard makes any retry idempotent.
 */
class ProcessBiometricPunch implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [10, 30, 90];

    public int $uniqueFor = 300;

    public function __construct(public int $punchId)
    {
        $this->onQueue('attendance');
    }

    public function uniqueId(): string
    {
        return 'biometric-punch:' . $this->punchId;
    }

    public function handle(BiometricAttendanceService $service): void
    {
        $punch = BiometricPunch::find($this->punchId);
        if (! $punch) {
            return;
        }

        $service->apply($punch);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessBiometricPunch failed', [
            'punch_id' => $this->punchId,
            'error' => $e->getMessage(),
        ]);
    }
}
