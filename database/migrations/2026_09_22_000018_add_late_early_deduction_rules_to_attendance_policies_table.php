<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalizes the late-only, half-day-only policy into two independent rule
 * sets (late arrival / early leaving), each with its own monthly allowance,
 * an attendance-status action (none/half_day/absent), and an independent
 * payroll deduction (excess_days * daily_rate * multiplier).
 *
 * grace_mode defaults to 'fixed', backfilled from the existing grace_minutes
 * value — this reproduces today's live gate (LatePolicyService::baseStatus(),
 * $policy->isLate($row->late_minutes)) with zero drift for every tenant that
 * doesn't touch the new settings. 'shift' mode (per-shift grace_minutes) is a
 * new opt-in capability, not a default.
 *
 * late_halfday_enabled / monthly_late_allowance are kept (audit/back-compat);
 * late_halfday_enabled=1 rows are backfilled to late_attendance_action='half_day'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_policies', 'grace_mode')) {
                $table->enum('grace_mode', ['shift', 'fixed'])->default('fixed')->after('grace_minutes');
            }
            if (! Schema::hasColumn('attendance_policies', 'fixed_grace_minutes')) {
                $table->unsignedSmallInteger('fixed_grace_minutes')->default(0)->after('grace_mode');
            }
            if (! Schema::hasColumn('attendance_policies', 'late_attendance_action')) {
                $table->enum('late_attendance_action', ['none', 'half_day', 'absent'])->default('none')->after('late_halfday_enabled');
            }
            if (! Schema::hasColumn('attendance_policies', 'late_deduction_enabled')) {
                $table->boolean('late_deduction_enabled')->default(false)->after('late_attendance_action');
            }
            if (! Schema::hasColumn('attendance_policies', 'late_deduction_multiplier')) {
                $table->decimal('late_deduction_multiplier', 4, 2)->default(1.00)->after('late_deduction_enabled');
            }
            if (! Schema::hasColumn('attendance_policies', 'monthly_early_allowance')) {
                $table->unsignedSmallInteger('monthly_early_allowance')->default(30)->after('late_deduction_multiplier');
            }
            if (! Schema::hasColumn('attendance_policies', 'early_attendance_action')) {
                $table->enum('early_attendance_action', ['none', 'half_day', 'absent'])->default('none')->after('monthly_early_allowance');
            }
            if (! Schema::hasColumn('attendance_policies', 'early_deduction_enabled')) {
                $table->boolean('early_deduction_enabled')->default(false)->after('early_attendance_action');
            }
            if (! Schema::hasColumn('attendance_policies', 'early_deduction_multiplier')) {
                $table->decimal('early_deduction_multiplier', 4, 2)->default(1.00)->after('early_deduction_enabled');
            }
        });

        // Existing rows only — new rows already get correct defaults from the schema above.
        DB::table('attendance_policies')->update([
            'fixed_grace_minutes' => DB::raw('COALESCE(grace_minutes, 0)'),
        ]);
        DB::table('attendance_policies')->where('late_halfday_enabled', 1)->update([
            'late_attendance_action' => 'half_day',
        ]);
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn([
                'grace_mode', 'fixed_grace_minutes',
                'late_attendance_action', 'late_deduction_enabled', 'late_deduction_multiplier',
                'monthly_early_allowance', 'early_attendance_action', 'early_deduction_enabled', 'early_deduction_multiplier',
            ]);
        });
    }
};
