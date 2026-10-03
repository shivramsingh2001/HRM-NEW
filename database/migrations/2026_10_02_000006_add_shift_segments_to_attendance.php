<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-shift Phase 3: every punch knows which shift it belongs to, and a day
 * worked across several shifts keeps per-shift numbers.
 *
 *  - attendance_punches.user_shift_id: the user_shifts row the punch was
 *    matched to (null = fixed company shift / no shift).
 *  - attendances.shift_count / expected_minutes / extra_shift_minutes:
 *    day-level roll-ups (extra_shift_minutes = minutes worked in 2nd+ shifts,
 *    paid as overtime from Phase 4).
 *  - attendance_shift_segments: one row per shift worked that day.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_punches', 'user_shift_id')) {
            Schema::table('attendance_punches', function (Blueprint $table) {
                $table->unsignedBigInteger('user_shift_id')->nullable()->after('attendance_id');
                $table->index('user_shift_id', 'punch_user_shift_idx');
            });
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'shift_count')) {
                $table->unsignedTinyInteger('shift_count')->nullable()->after('session_count');
            }
            if (! Schema::hasColumn('attendances', 'expected_minutes')) {
                $table->unsignedInteger('expected_minutes')->nullable()->after('shift_count');
            }
            if (! Schema::hasColumn('attendances', 'extra_shift_minutes')) {
                $table->unsignedInteger('extra_shift_minutes')->default(0)->after('expected_minutes');
            }
        });

        if (! Schema::hasTable('attendance_shift_segments')) {
            Schema::create('attendance_shift_segments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('attendance_id');
                $table->unsignedBigInteger('user_id');
                $table->date('date');
                $table->unsignedBigInteger('user_shift_id')->nullable();
                $table->unsignedBigInteger('shift_id')->nullable();
                $table->boolean('is_additional')->default(false);
                $table->dateTime('scheduled_start')->nullable();
                $table->dateTime('scheduled_end')->nullable();
                $table->dateTime('first_in')->nullable();
                $table->dateTime('last_out')->nullable();
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('expected_minutes')->default(0);
                $table->unsignedInteger('late_minutes')->default(0);
                $table->unsignedInteger('early_departure_minutes')->default(0);
                $table->unsignedInteger('overtime_minutes')->default(0);
                $table->unsignedTinyInteger('session_count')->default(0);
                $table->boolean('is_open')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'user_id', 'date'], 'seg_tenant_user_date_idx');
                $table->index('attendance_id', 'seg_attendance_idx');
                $table->foreign('attendance_id', 'seg_attendance_fk')->references('id')->on('attendances')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_shift_segments');

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['extra_shift_minutes', 'expected_minutes', 'shift_count'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('attendance_punches', 'user_shift_id')) {
            Schema::table('attendance_punches', function (Blueprint $table) {
                $table->dropIndex('punch_user_shift_idx');
                $table->dropColumn('user_shift_id');
            });
        }
    }
};
