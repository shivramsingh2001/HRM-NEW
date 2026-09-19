<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points a materialized `user_shifts` day-cache row back at the
 * `shift_assignments` row that produced it, so the Existing Assignments UI
 * can show type/status without a join. Null for every pre-existing row and
 * for rows written by paths that don't go through ShiftMaterializer
 * (e.g. a raw single-day `updateUserShift` edit) — that's expected, not a bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_shifts', 'shift_assignment_id')) {
                $table->unsignedBigInteger('shift_assignment_id')->nullable()->after('shift_id');
                $table->index('shift_assignment_id', 'user_shifts_shift_assignment_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_shifts', function (Blueprint $table) {
            if (Schema::hasColumn('user_shifts', 'shift_assignment_id')) {
                $table->dropIndex('user_shifts_shift_assignment_id_idx');
                $table->dropColumn('shift_assignment_id');
            }
        });
    }
};
