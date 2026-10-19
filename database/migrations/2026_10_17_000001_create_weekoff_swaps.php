<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Week-off swap (plan A1, 2026-10-17): employees work a week-off (e.g. Sat 17 Oct)
 * and get another day off instead (e.g. Mon 19 Oct). One `weekoff_swaps` row per
 * swap the admin saves; one `weekoff_swap_employees` row per employee, holding the
 * replacement day and the one-day date_based `user_weekoffs` row it created
 * (deleted again when the swap is cancelled). New tables only — nothing existing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekoff_swaps')) {
            Schema::create('weekoff_swaps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->date('worked_date');
                $table->date('replacement_date');
                $table->string('reason', 500)->nullable();
                $table->enum('status', ['active', 'cancelled'])->default('active');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'worked_date'], 'weekoff_swaps_tenant_worked_idx');
            });
        }

        if (! Schema::hasTable('weekoff_swap_employees')) {
            Schema::create('weekoff_swap_employees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('weekoff_swap_id')->constrained('weekoff_swaps')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id');
                $table->date('worked_date');
                $table->date('replacement_date');
                $table->unsignedBigInteger('user_weekoff_id')->nullable();
                $table->enum('status', ['active', 'cancelled'])->default('active');
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->unique(['weekoff_swap_id', 'user_id'], 'weekoff_swap_employees_swap_user_uq');
                $table->index(['tenant_id', 'user_id', 'replacement_date'], 'weekoff_swap_employees_user_day_idx');
                $table->index('user_weekoff_id', 'weekoff_swap_employees_weekoff_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weekoff_swap_employees');
        Schema::dropIfExists('weekoff_swaps');
    }
};
