<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll rebuild — Phase 3. A simple, self-contained per-tenant gate for
     * the new dynamic-payroll admin UI (component catalog / structure
     * builder), so it doesn't appear for tenants who haven't been backfilled
     * / aren't ready for it yet. Deliberately NOT wired into the cross-app
     * feature_registry/FeatureService system (that table is shared with the
     * separate hrm-superadmin app and subscription plans — a new entry
     * there is a superadmin-panel change, out of scope here); Phase 8's
     * real cutover flag can migrate to that system later if desired.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'payroll_dynamic_ui_enabled')) {
            Schema::table('tenants', function (Blueprint $table) {
                // No ->after(): 'sandwich' doesn't actually exist on tenants
                // (confirmed during this same rebuild's sandwich-rule bug
                // fix — the real column is attendance_policies.sandwich_leave)
                // and this migration silently failed on that account every
                // time it was run until this fix.
                $table->boolean('payroll_dynamic_ui_enabled')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'payroll_dynamic_ui_enabled')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('payroll_dynamic_ui_enabled');
            });
        }
    }
};
