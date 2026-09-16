<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Professional Tax is genuinely row-per-band and state-specific (India),
     * hence a tabular slab table rather than JSON — seeded with major-state
     * defaults, tenant-editable. Resolution uses tenants.state (confirmed to
     * already exist during Phase 0) as the default work state.
     */
    public function up(): void
    {
        if (Schema::hasTable('statutory_pt_slabs')) {
            return;
        }

        Schema::create('statutory_pt_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('state_code', 10);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('gross_salary_min', 15, 2);
            $table->decimal('gross_salary_max', 15, 2)->nullable();
            $table->decimal('pt_amount', 10, 2);
            $table->enum('gender', ['all', 'male', 'female'])->default('all');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'state_code', 'effective_from'], 'pt_slabs_tenant_state_effective_idx');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_pt_slabs');
    }
};
