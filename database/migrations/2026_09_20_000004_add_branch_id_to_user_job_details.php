<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional organizational Branch membership — separate from the existing
 * `office_branch` column (which is for attendance geofencing only, see
 * attendance_locations). A single-location company never populates this;
 * companies with multiple physical branches assign one per employee here.
 * No FK constraint, matching this table's existing legacy columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_job_details', 'branch_id')) {
            Schema::table('user_job_details', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')
                    ->nullable()
                    ->after('office_branch')
                    ->comment('organizational Branch (company_branches.id) — distinct from office_branch, which is for attendance geofencing');
                $table->index('branch_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_job_details', 'branch_id')) {
            Schema::table('user_job_details', function (Blueprint $table) {
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
