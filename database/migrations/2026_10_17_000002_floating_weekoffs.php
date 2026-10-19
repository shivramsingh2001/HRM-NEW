<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Floating week-offs (plan C1, 2026-10-17): "N week-offs a month on any day".
 * Company Policies switch + quota on attendance_policies (so Employee 360 can
 * override them per employee), and `floating_weekoff_days` — one row per day
 * the nightly allocator turned into a week-off, pointing at the one-day
 * date_based `user_weekoffs` row it wrote (removed again when re-allocated).
 * Defaults keep every company exactly as before (switch off).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_policies', 'floating_weekoffs_enabled')) {
                $table->boolean('floating_weekoffs_enabled')->default(false);
            }
            if (! Schema::hasColumn('attendance_policies', 'floating_weekoffs_per_month')) {
                $table->unsignedTinyInteger('floating_weekoffs_per_month')->default(4);
            }
            if (! Schema::hasColumn('attendance_policies', 'floating_weekoffs_max_per_week')) {
                $table->unsignedTinyInteger('floating_weekoffs_max_per_week')->default(0);   // 0 = no limit
            }
        });

        if (! Schema::hasTable('floating_weekoff_days')) {
            Schema::create('floating_weekoff_days', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->date('date');
                $table->unsignedBigInteger('user_weekoff_id')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'date'], 'floating_weekoff_days_user_date_uq');
                $table->index(['tenant_id', 'user_id', 'date'], 'floating_weekoff_days_tenant_user_date_idx');
                $table->index('user_weekoff_id', 'floating_weekoff_days_weekoff_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('floating_weekoff_days');
        Schema::table('attendance_policies', function (Blueprint $table) {
            foreach (['floating_weekoffs_enabled', 'floating_weekoffs_per_month', 'floating_weekoffs_max_per_week'] as $col) {
                if (Schema::hasColumn('attendance_policies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
