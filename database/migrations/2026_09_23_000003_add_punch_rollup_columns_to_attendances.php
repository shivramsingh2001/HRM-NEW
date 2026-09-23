<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `attendances` becomes a materialised rollup of `attendance_punches` (for
 * both single- and multi-punch tenants). These two additive columns let
 * UI/reports cheaply know whether a day has more than one session to drill
 * into, without changing any existing column's meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'session_count')) {
                $table->unsignedTinyInteger('session_count')->nullable()->after('worked_hours');
            }
            if (!Schema::hasColumn('attendances', 'punches_last_synced_at')) {
                $table->timestamp('punches_last_synced_at')->nullable()->after('session_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            foreach (['punches_last_synced_at', 'session_count'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
