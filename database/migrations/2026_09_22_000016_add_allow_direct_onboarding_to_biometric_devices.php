<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device opt-in: when true, a device-side enrollment the bridge reports
 * with no matching HRM user (BiometricV1Controller::reportEnrollments) may
 * auto-create a new HRM employee instead of staying an unmapped row. Default
 * false — auto-creating real login accounts is higher risk than roster
 * provisioning and must be an explicit per-device choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('biometric_devices', 'allow_direct_onboarding')) {
                $table->boolean('allow_direct_onboarding')->default(false)->after('default_privilege');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropColumn('allow_direct_onboarding');
        });
    }
};
