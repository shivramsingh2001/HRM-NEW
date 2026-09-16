<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant/region-configurable PF/ESI/PT/TDS rates and wage ceilings,
     * versioned by effective date range so a rate change never rewrites
     * history. Replaces the hardcoded ESI ₹21,000 ceiling literal that
     * currently lives in add-user.blade.php / update-user.blade.php.
     */
    public function up(): void
    {
        if (Schema::hasTable('statutory_rate_configs')) {
            return;
        }

        Schema::create('statutory_rate_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->enum('statutory_type', ['pf', 'esi', 'pt', 'tds', 'lwf']);
            $table->string('region_code', 10)->nullable(); // state code, when applicable
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('config'); // e.g. {employee_rate, employer_rate, wage_ceiling, ...}
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'statutory_type', 'effective_from'], 'src_tenant_type_effective_idx');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_rate_configs');
    }
};
