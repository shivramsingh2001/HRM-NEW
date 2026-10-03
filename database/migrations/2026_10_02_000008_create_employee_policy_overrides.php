<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee 360 Phase 3: a company policy value changed for ONE employee.
 * One row per (employee, section, key); no row = the company value applies.
 * Sections: attendance, overtime, performance, leave (key "type:{leave_type_id}").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_policy_overrides')) {
            return;
        }

        Schema::create('employee_policy_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('section', 30);
            $table->string('key', 60);
            $table->json('value');
            $table->unsignedBigInteger('set_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'section', 'key'], 'emp_policy_override_uq');
            $table->index(['tenant_id', 'section'], 'emp_policy_override_section_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_policy_overrides');
    }
};
