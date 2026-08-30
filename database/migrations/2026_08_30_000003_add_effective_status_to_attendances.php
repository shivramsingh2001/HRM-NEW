<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy-resolved day status, written ONLY by LatePolicyService::recalculateMonth().
 *
 *   attendance_status  = raw fact from the punch (present / late / ...)
 *   effective_status   = after applying the tenant's monthly late allowance
 *   day_fraction       = 1.00 present / 0.50 half / 0.00 absent|leave — for summary math
 *
 * A unique (tenant_id, user_id, date) index is intentionally NOT added here:
 * historical data contains duplicate rows (the concurrent-clock-in race). That
 * de-dupe + unique index is handled in the Phase 4 backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'effective_status')) {
                $table->string('effective_status', 30)->nullable()->after('attendance_status');
            }
            if (!Schema::hasColumn('attendances', 'day_fraction')) {
                $table->decimal('day_fraction', 3, 2)->default(1.00)->after('effective_status');
            }
            if (!Schema::hasColumn('attendances', 'policy_note')) {
                $table->string('policy_note', 255)->nullable()->after('day_fraction');
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['tenant_id', 'user_id', 'date'], 'attn_tenant_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attn_tenant_user_date_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['policy_note', 'day_fraction', 'effective_status'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
