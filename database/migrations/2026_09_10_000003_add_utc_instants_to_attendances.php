<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 1 / W4 — store attendance punches as true UTC instants alongside the
 * legacy local-time strings.
 *
 * `attendances.clock_in` / `clock_out` are varchar local-time and code depends
 * on the raw string form, so a type change is unsafe. These parallel columns
 * are populated by every writer and back-filled by `attendance:backfill-utc`.
 * A later release makes clock_in/clock_out accessors off the UTC columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('id');
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'clock_in_utc')) {
                $table->timestamp('clock_in_utc')->nullable()->after('clock_in');
            }
            if (! Schema::hasColumn('attendances', 'clock_out_utc')) {
                $table->timestamp('clock_out_utc')->nullable()->after('clock_out');
            }
            if (! Schema::hasColumn('attendances', 'tz')) {
                // The zone the local strings on this row were captured in.
                $table->string('tz', 64)->nullable()->after('clock_out_utc');
            }
        });

        // Range queries on the real instant.
        $hasIndex = collect(Schema::getConnection()->select('SHOW INDEX FROM attendances'))
            ->contains(fn ($r) => $r->Key_name === 'attn_tenant_user_clockinutc_idx');
        if (! $hasIndex) {
            Schema::getConnection()->statement(
                'ALTER TABLE attendances ADD INDEX attn_tenant_user_clockinutc_idx (tenant_id, user_id, clock_in_utc)'
            );
        }
    }

    public function down(): void
    {
        $hasIndex = collect(Schema::getConnection()->select('SHOW INDEX FROM attendances'))
            ->contains(fn ($r) => $r->Key_name === 'attn_tenant_user_clockinutc_idx');
        if ($hasIndex) {
            Schema::getConnection()->statement('ALTER TABLE attendances DROP INDEX attn_tenant_user_clockinutc_idx');
        }

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['clock_in_utc', 'clock_out_utc', 'tz'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
