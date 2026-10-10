<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotating shift patterns: a pattern's days off are written as one-day
 * date_based user_weekoffs rows (so attendance, payroll and every other
 * week-off reader treat them as week-offs without any change), tagged with
 * the rotating assignment that generated them so they can be removed again
 * when the rotation ends or is replaced. NULL = a week-off entered by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_weekoffs', function (Blueprint $table) {
            if (! Schema::hasColumn('user_weekoffs', 'shift_assignment_id')) {
                $table->unsignedBigInteger('shift_assignment_id')->nullable()->after('user_id');
                $table->index('shift_assignment_id', 'user_weekoffs_assignment_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_weekoffs', function (Blueprint $table) {
            if (Schema::hasColumn('user_weekoffs', 'shift_assignment_id')) {
                $table->dropIndex('user_weekoffs_assignment_idx');
                $table->dropColumn('shift_assignment_id');
            }
        });
    }
};
