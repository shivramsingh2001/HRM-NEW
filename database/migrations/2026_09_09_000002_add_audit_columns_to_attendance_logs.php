<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turn `attendance_logs` into a real change ledger in addition to device
 * telemetry: record who made the change (actor), how (source), and the
 * before/after snapshot of the columns that changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_logs', 'actor_id')) {
                $table->unsignedBigInteger('actor_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('attendance_logs', 'actor_role')) {
                $table->string('actor_role', 20)->nullable()->after('actor_id');
            }
            if (!Schema::hasColumn('attendance_logs', 'source')) {
                // clock_in | clock_out | fingerprint | manual | regularization | auto_clockout | policy_recalc
                $table->string('source', 30)->nullable()->after('actor_role');
            }
            if (!Schema::hasColumn('attendance_logs', 'before')) {
                $table->json('before')->nullable()->after('raw_data');
            }
            if (!Schema::hasColumn('attendance_logs', 'after')) {
                $table->json('after')->nullable()->after('before');
            }
            if (!Schema::hasColumn('attendance_logs', 'reason')) {
                $table->string('reason', 500)->nullable()->after('after');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            foreach (['reason', 'after', 'before', 'source', 'actor_role', 'actor_id'] as $col) {
                if (Schema::hasColumn('attendance_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
