<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave carry forward. Company policy (Company Policies → Leave carry forward):
 * an on/off switch and the leave-year start; off (default) keeps today's
 * behaviour where every unused day stays in the balance. The per-type limit /
 * expiry already live on leave_types (2026_09_17_000001). leave_carry_forwards
 * holds one row per (employee, type, leave year) written by
 * `leaves:carry-forward` — the run's idempotency key and what the expiry step
 * reads back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'leave_carry_forward_enabled')) {
                $table->boolean('leave_carry_forward_enabled')->default(false);
            }
            // When the switch was last turned on: only a leave year that starts on or after
            // this date is carried forward (switching on mid-year never lapses retroactively).
            if (! Schema::hasColumn('tenants', 'leave_carry_forward_enabled_at')) {
                $table->timestamp('leave_carry_forward_enabled_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'leave_year_start_month')) {
                $table->unsignedTinyInteger('leave_year_start_month')->default(4);
            }
            if (! Schema::hasColumn('tenants', 'leave_year_start_day')) {
                $table->unsignedTinyInteger('leave_year_start_day')->default(1);
            }
        });

        if (! Schema::hasTable('leave_carry_forwards')) {
            Schema::create('leave_carry_forwards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('leave_type_id');
                $table->date('leave_year_start');
                $table->decimal('closing_balance', 8, 2)->default(0);
                $table->decimal('carry_limit', 8, 2)->nullable();
                $table->decimal('carried', 8, 2)->default(0);
                $table->decimal('lapsed', 8, 2)->default(0);
                $table->date('expires_on')->nullable();
                $table->decimal('expired', 8, 2)->default(0);
                $table->timestamp('expired_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id', 'leave_type_id', 'leave_year_start'], 'leave_cf_unique');
                $table->index(['tenant_id', 'expires_on'], 'leave_cf_expiry_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_carry_forwards');

        Schema::table('tenants', function (Blueprint $table) {
            foreach (['leave_carry_forward_enabled', 'leave_carry_forward_enabled_at', 'leave_year_start_month', 'leave_year_start_day'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
