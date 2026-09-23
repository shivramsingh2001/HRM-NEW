<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-configurable prefix for auto-generated employee IDs (e.g. 'SH' ->
 * SH000123). Was previously hardcoded in two places
 * (UserController::generateEmployeeId, EmployeeProvisioningService) — see
 * App\Services\User\EmployeeIdService, the single generator both now call.
 * Existing employees keep their already-assigned employee_id; changing this
 * only affects IDs generated after the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'employee_id_prefix')) {
                $table->string('employee_id_prefix', 2)->nullable()->after('notice_period');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'employee_id_prefix')) {
                $table->dropColumn('employee_id_prefix');
            }
        });
    }
};
