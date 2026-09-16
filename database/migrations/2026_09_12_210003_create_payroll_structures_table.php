<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named, reusable salary-structure templates (e.g. "Standard Tech
     * Employee", "Field Sales") — a bundle of chosen components. Replaces
     * the *role* of payroll_masters for tenants on the new dynamic engine,
     * without touching payroll_masters itself.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_structures')) {
            return;
        }

        Schema::create('payroll_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code', 60)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('payroll_calculation_type', ['day_based', 'hour_based'])->default('day_based');
            $table->decimal('working_hours_per_day', 5, 2)->nullable();
            $table->decimal('hourly_rate_applied', 10, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_structures');
    }
};
