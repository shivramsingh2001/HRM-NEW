<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comp-off (plan B, 2026-10-17): working a week-off or holiday earns a day off
 * later. Company Policies switch + rules on attendance_policies (so Employee 360
 * can override them per employee) and `comp_off_credits`, one row per worked
 * off-day claimed (pending → approved → expired, or rejected / cancelled). An
 * approved credit adds to the balance of the system "Comp Off" leave type
 * (code comp_off), which is spent through the normal leave application.
 * Defaults keep every company as before (switch off).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_policies', 'comp_off_enabled')) {
                $table->boolean('comp_off_enabled')->default(false);
                $table->enum('comp_off_mode', ['request', 'auto'])->default('request');
                $table->decimal('comp_off_full_day_hours', 4, 2)->default(8);
                $table->decimal('comp_off_half_day_hours', 4, 2)->default(4);
                $table->unsignedSmallInteger('comp_off_expiry_days')->default(60);   // 0 = never
                $table->unsignedTinyInteger('comp_off_monthly_cap')->default(0);    // days; 0 = no cap
            }
        });

        if (! Schema::hasTable('comp_off_credits')) {
            Schema::create('comp_off_credits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->date('worked_date');
                $table->enum('day_type', ['week_off', 'holiday']);
                $table->decimal('worked_hours', 5, 2)->default(0);
                $table->decimal('days', 3, 1);
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'expired'])->default('pending');
                $table->enum('source', ['request', 'auto'])->default('request');
                $table->string('reason', 500)->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->string('decision_remarks', 500)->nullable();
                $table->date('expires_on')->nullable();
                $table->decimal('lapsed_days', 3, 1)->default(0);
                $table->unsignedBigInteger('leave_type_id')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'user_id', 'worked_date'], 'comp_off_credits_user_day_idx');
                $table->index(['tenant_id', 'status', 'expires_on'], 'comp_off_credits_status_expiry_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comp_off_credits');
        Schema::table('attendance_policies', function (Blueprint $table) {
            foreach (['comp_off_enabled', 'comp_off_mode', 'comp_off_full_day_hours', 'comp_off_half_day_hours', 'comp_off_expiry_days', 'comp_off_monthly_cap'] as $col) {
                if (Schema::hasColumn('attendance_policies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
