<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-shift Phase 4: a regularization request can name the shift it
 * corrects (null = the day's primary shift, as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_regularizations', 'user_shift_id')) {
            Schema::table('attendance_regularizations', function (Blueprint $table) {
                $table->unsignedBigInteger('user_shift_id')->nullable()->after('date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_regularizations', 'user_shift_id')) {
            Schema::table('attendance_regularizations', function (Blueprint $table) {
                $table->dropColumn('user_shift_id');
            });
        }
    }
};
