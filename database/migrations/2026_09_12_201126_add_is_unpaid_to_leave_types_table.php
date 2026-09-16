<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('leave_types', 'is_unpaid')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->boolean('is_unpaid')->default(false)->after('code');
            });
        }

        // Backfill from the code='lwp' marker already set by
        // 2026_09_12_204257_add_code_to_leave_types_table — replaces the
        // payroll engine's old hardcoded `$unpaidLeaveTypeIds = [3]`, which
        // only happened to be correct for the one tenant whose LWP row's id
        // was literally 3.
        DB::table('leave_types')->where('code', 'lwp')->update(['is_unpaid' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('leave_types', 'is_unpaid')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('is_unpaid');
            });
        }
    }
};
