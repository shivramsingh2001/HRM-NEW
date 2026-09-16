<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll Audit Phase 4 — L3. Both statutory tables only had a plain
     * (non-unique) index, so nothing prevented two overlapping rate/slab
     * versions for the same effective date. The unique key follows directly
     * from each table's own columns, which already encode the real
     * versioning granularity:
     *   - statutory_pt_slabs: a slab is uniquely identified by tenant +
     *     state + gender (PT can vary by gender in some states) + the
     *     salary band it starts at + when it took effect.
     *   - statutory_rate_configs: a rate is uniquely identified by tenant +
     *     statutory type + region + when it took effect.
     * Confirmed no existing duplicates in live data for either table.
     */
    public function up(): void
    {
        Schema::table('statutory_pt_slabs', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'state_code', 'gender', 'gross_salary_min', 'effective_from'],
                'statutory_pt_slabs_version_unique'
            );
        });

        Schema::table('statutory_rate_configs', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'statutory_type', 'region_code', 'effective_from'],
                'statutory_rate_configs_version_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('statutory_pt_slabs', function (Blueprint $table) {
            $table->dropUnique('statutory_pt_slabs_version_unique');
        });

        Schema::table('statutory_rate_configs', function (Blueprint $table) {
            $table->dropUnique('statutory_rate_configs_version_unique');
        });
    }
};
