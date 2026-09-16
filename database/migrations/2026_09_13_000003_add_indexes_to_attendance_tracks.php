<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the GPS-breadcrumb read paths (today-locations, location/tracks,
 * ai/attendance-locations, attendance/sessions) and the meter/prune commands.
 *
 * attendance_tracks has no create migration in this repo (base schema lives in
 * the prod DB), so guard hasTable + SHOW INDEX.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_tracks')) {
            return;
        }

        if (! $this->indexExists('attendance_tracks', 'atk_attendance_time_idx')) {
            DB::statement('ALTER TABLE attendance_tracks ADD INDEX atk_attendance_time_idx (attendance_id, track_time)');
        }
        if (! $this->indexExists('attendance_tracks', 'atk_tenant_user_time_idx')) {
            DB::statement('ALTER TABLE attendance_tracks ADD INDEX atk_tenant_user_time_idx (tenant_id, user_id, track_time)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_tracks')) {
            return;
        }

        if ($this->indexExists('attendance_tracks', 'atk_tenant_user_time_idx')) {
            DB::statement('ALTER TABLE attendance_tracks DROP INDEX atk_tenant_user_time_idx');
        }
        if ($this->indexExists('attendance_tracks', 'atk_attendance_time_idx')) {
            DB::statement('ALTER TABLE attendance_tracks DROP INDEX atk_attendance_time_idx');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains(fn ($row) => $row->Key_name === $index);
    }
};
