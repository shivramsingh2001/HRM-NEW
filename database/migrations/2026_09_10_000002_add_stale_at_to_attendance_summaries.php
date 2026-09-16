<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 1 / W2 + W3 — freshness marker for attendance_summaries.
 *
 * A write marks the affected (user, month) row stale synchronously; the async
 * RecalculateAttendanceMonth job (or the next summary read) clears it by
 * writing a newer calculated_at. A row is fresh when
 * calculated_at >= coalesce(stale_at, calculated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_summaries', 'stale_at')) {
                $table->timestamp('stale_at')->nullable()->after('calculated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_summaries', 'stale_at')) {
                $table->dropColumn('stale_at');
            }
        });
    }
};
