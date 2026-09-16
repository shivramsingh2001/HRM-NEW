<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the unfinished CAMS "RealTime" fingerprint pipeline tables. Replaced
 * by the SBXPC biometric integration (biometric_devices / biometric_enrollments
 * / biometric_punches). Guarded — these tables may never have been created in
 * this environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('device_user_maps');
        Schema::dropIfExists('fingerprint_punch_logs');
        Schema::dropIfExists('fingerprint_devices');
    }

    public function down(): void
    {
        // No-op: the biometric_* tables replace these; recreating the old
        // (never-migrated) schema is not meaningful.
    }
};
