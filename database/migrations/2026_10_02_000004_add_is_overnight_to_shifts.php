<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit "Overnight shift (ends next day)" flag. Until now a shift was
 * overnight only by inference (end_time <= start_time); existing rows are
 * backfilled with that same rule so behaviour does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shifts', 'is_overnight')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->boolean('is_overnight')->default(false)->after('end_time');
            });
        }

        DB::table('shifts')->whereColumn('end_time', '<=', 'start_time')->update(['is_overnight' => 1]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('shifts', 'is_overnight')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->dropColumn('is_overnight');
            });
        }
    }
};
