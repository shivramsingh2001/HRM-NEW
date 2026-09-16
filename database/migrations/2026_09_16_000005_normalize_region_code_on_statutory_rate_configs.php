<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Payroll Audit Phase 4 — L3 follow-up. `region_code` was nullable with
     * no default, and MySQL/MariaDB treat NULL as distinct in a unique
     * index -- so the statutory_rate_configs_version_unique constraint added
     * in the previous migration silently didn't catch duplicates whenever
     * region_code was NULL, which is the common case (PF/ESI aren't
     * region-specific for most tenants). Normalizing to NOT NULL DEFAULT ''
     * makes the unique constraint actually work for that common case.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE statutory_rate_configs SET region_code = '' WHERE region_code IS NULL");
        DB::statement("ALTER TABLE statutory_rate_configs MODIFY COLUMN region_code VARCHAR(10) NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE statutory_rate_configs MODIFY COLUMN region_code VARCHAR(10) NULL DEFAULT NULL");
    }
};
