<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE job_offers MODIFY COLUMN employment_type ENUM('full_time','part_time','contract','internship','temporary') NOT NULL DEFAULT 'full_time'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE job_offers MODIFY COLUMN employment_type ENUM('full_time','part_time','contract','internship') NOT NULL DEFAULT 'full_time'");
    }
};
