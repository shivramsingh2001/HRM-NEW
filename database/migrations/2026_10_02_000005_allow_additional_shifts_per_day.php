<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-shift Phase 2: an employee may work more than one shift on a date.
 *
 * Each date keeps exactly ONE primary row (is_additional = 0) — the shift
 * attendance/payroll measure against, same as before. 2nd+ shifts are extra
 * rows with is_additional = 1. The old UNIQUE(user_id, date) becomes
 * UNIQUE(user_id, date, primary_slot) where primary_slot is 1 for the primary
 * row and NULL for additional rows, so "one primary per day" is still enforced
 * by the DB (ShiftMaterializer relies on the duplicate-key error) while any
 * number of additional rows are allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_shifts', 'is_additional')) {
            Schema::table('user_shifts', function (Blueprint $table) {
                $table->boolean('is_additional')->default(false)->after('shift_assignment_id');
            });
        }

        if (! Schema::hasColumn('user_shifts', 'primary_slot')) {
            Schema::table('user_shifts', function (Blueprint $table) {
                $table->tinyInteger('primary_slot')->nullable()->storedAs('IF(`is_additional` = 0, 1, NULL)')->after('is_additional');
            });
        }

        if (! $this->hasIndex('user_shifts', 'user_shifts_user_date_primary_unique')) {
            Schema::table('user_shifts', function (Blueprint $table) {
                $table->unique(['user_id', 'date', 'primary_slot'], 'user_shifts_user_date_primary_unique');
            });
        }

        // The new index also starts with user_id, so the user_id FK keeps an index.
        if ($this->hasIndex('user_shifts', 'user_shifts_user_id_date_unique')) {
            Schema::table('user_shifts', function (Blueprint $table) {
                $table->dropUnique('user_shifts_user_id_date_unique');
            });
        }

        if (! Schema::hasColumn('shift_assignments', 'is_additional')) {
            Schema::table('shift_assignments', function (Blueprint $table) {
                $table->boolean('is_additional')->default(false)->after('type');
            });
        }
    }

    public function down(): void
    {
        // Additional rows would break the old one-row-per-day key.
        \Illuminate\Support\Facades\DB::table('user_shifts')->where('is_additional', 1)->delete();

        if (! $this->hasIndex('user_shifts', 'user_shifts_user_id_date_unique')) {
            Schema::table('user_shifts', function (Blueprint $table) {
                $table->unique(['user_id', 'date'], 'user_shifts_user_id_date_unique');
            });
        }
        if ($this->hasIndex('user_shifts', 'user_shifts_user_date_primary_unique')) {
            Schema::table('user_shifts', function (Blueprint $table) {
                $table->dropUnique('user_shifts_user_date_primary_unique');
            });
        }
        Schema::table('user_shifts', function (Blueprint $table) {
            $table->dropColumn(['primary_slot', 'is_additional']);
        });
        if (Schema::hasColumn('shift_assignments', 'is_additional')) {
            Schema::table('shift_assignments', function (Blueprint $table) {
                $table->dropColumn('is_additional');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($i) => $i['name'] === $index);
    }
};
