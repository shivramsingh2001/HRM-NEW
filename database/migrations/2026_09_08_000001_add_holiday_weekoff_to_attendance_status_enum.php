<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add holiday / weekoff to attendances.attendance_status so an admin/HR/manager
 * can manually record a single employee's day as a holiday or a week off from the
 * team screen (Feature A). The column stays NOT NULL DEFAULT 'present'.
 */
return new class extends Migration
{
    private string $withNew = "'present','absent','half_day','late','early_departure','overtime','on_leave','first_half_leave','second_half_leave','holiday','weekoff'";

    private string $previous = "'present','absent','half_day','late','early_departure','overtime','on_leave','first_half_leave','second_half_leave'";

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
            ->whereIn('attendance_status', ['holiday', 'weekoff'])
            ->update(['attendance_status' => 'absent']);

        DB::statement("ALTER TABLE attendances MODIFY COLUMN attendance_status ENUM({$this->previous}) NOT NULL DEFAULT 'present'");
    }
};
