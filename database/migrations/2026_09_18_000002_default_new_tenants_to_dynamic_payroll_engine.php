<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The legacy Payroll Masters/Employee Payroll screens are removed from
     * this codebase -- a tenant provisioned from here on has no legacy UI to
     * fall back to, so it must start on the dynamic engine. Tenant
     * provisioning itself lives in the separate hrm-superadmin app; changing
     * the column default here is the one place this app can make that
     * guarantee regardless of what that app's INSERT does or doesn't set.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE tenants ALTER COLUMN payroll_dynamic_ui_enabled SET DEFAULT 1');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE tenants ALTER COLUMN payroll_dynamic_ui_enabled SET DEFAULT 0');
    }
};
