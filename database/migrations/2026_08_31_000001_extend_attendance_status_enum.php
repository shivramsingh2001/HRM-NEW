<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add leave markings to attendances.attendance_status so an admin/HR/manager can
 * manually record a day as on_leave / first_half_leave / second_half_leave
 * (Feature A). The column stays NOT NULL DEFAULT 'present'.
 */
return new class extends Migration
{
    private string $withNew = "'present','absent','half_day','late','early_departure','overtime','on_leave','first_half_leave','second_half_leave'";
    private string $original = "'present','absent','half_day','late','early_departure','overtime'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE attendances MODIFY COLUMN attendance_status ENUM({$this->withNew}) NOT NULL DEFAULT 'present'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Collapse the new values before shrinking the enum.
        DB::table('attendances')
            ->whereIn('attendance_status', ['on_leave', 'first_half_leave', 'second_half_leave'])
            ->update(['attendance_status' => 'absent']);

        DB::statement("ALTER TABLE attendances MODIFY COLUMN attendance_status ENUM({$this->original}) NOT NULL DEFAULT 'present'");
    }
};
