<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee 360 Phase 4: company-wide limits on WFH and regularization requests
 * (Company Policies → Request limits). NULL / 0 = no limit, so every existing
 * company keeps today's behaviour. One employee can get their own value on the
 * Employee 360 → Policies tab (employee_policy_overrides, section "requests").
 */
return new class extends Migration
{
    private const COLUMNS = [
        'wfh_max_days_per_month',
        'wfh_min_notice_days',
        'regularization_max_per_month',
        'regularization_max_days_back',
    ];

    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (! Schema::hasColumn('tenants', $column)) {
                    $table->unsignedSmallInteger($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
