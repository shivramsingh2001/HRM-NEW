<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-shift days: one shift worked is the day's normal shift (the main shift
 * when worked, else the first shift worked); every other shift worked is EXTRA
 * — paid as overtime or an extra working day per Company Policies.
 * `is_additional` stays "assigned as additional in the roster" (display);
 * `is_extra` is "paid as extra". Existing days are back-filled with the same
 * rule, and attendances.extra_shift_minutes re-summed from the extra shifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_shift_segments', 'is_extra')) {
            Schema::table('attendance_shift_segments', function (Blueprint $table) {
                $table->boolean('is_extra')->default(false)->after('is_additional');
            });
        }

        DB::table('attendance_shift_segments')
            ->orderBy('attendance_id')
            ->get(['id', 'attendance_id', 'is_additional', 'scheduled_start', 'worked_minutes'])
            ->groupBy('attendance_id')
            ->each(function ($segments, $attendanceId) {
                // Main shift first, then the rest in start order — the first one is the normal shift.
                $ordered = $segments->sortBy(fn ($s) => [(int) $s->is_additional, (string) $s->scheduled_start, $s->id])->values();
                $extraIds = $ordered->slice(1)->pluck('id');
                DB::table('attendance_shift_segments')->where('attendance_id', $attendanceId)
                    ->update(['is_extra' => 0]);
                if ($extraIds->isNotEmpty()) {
                    DB::table('attendance_shift_segments')->whereIn('id', $extraIds)->update(['is_extra' => 1]);
                }
                DB::table('attendances')->where('id', $attendanceId)
                    ->update(['extra_shift_minutes' => (int) $ordered->slice(1)->sum('worked_minutes')]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_shift_segments', 'is_extra')) {
            Schema::table('attendance_shift_segments', function (Blueprint $table) {
                $table->dropColumn('is_extra');
            });
        }
    }
};
