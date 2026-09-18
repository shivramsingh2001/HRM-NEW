<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi reporting-head support. `user_job_details.reporting_head` stays
     * as the denormalized "primary" reporting head (kept in sync by the
     * employee wizard) so the ~100 existing single-head read call sites
     * keep working unchanged; this pivot table is the source of truth for
     * the full set. See docs/modules.md "Users / Employee Profiles".
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_reporting_heads')) {
            Schema::create('user_reporting_heads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('reporting_head_id');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('reporting_head_id')->references('id')->on('users')->onDelete('cascade');

                $table->unique(['user_id', 'reporting_head_id']);
                $table->index('reporting_head_id');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reporting_heads');
    }
};
