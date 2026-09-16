<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly field-tracking usage per tenant — the auditable record the external
 * billing system reconciles against. Written by `field-tracking:meter`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('field_tracking_usage')) {
            return;
        }

        Schema::create('field_tracking_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->char('year_month', 7);                     // "2026-09"
            $table->unsignedInteger('seats_purchased')->default(0);
            $table->unsignedInteger('peak_seats_used')->default(0);
            $table->decimal('avg_seats_used', 6, 2)->default(0);
            $table->unsignedInteger('samples')->default(0);
            $table->json('enabled_user_ids')->nullable();
            $table->unsignedBigInteger('track_rows_written')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'year_month'], 'ftu_tenant_month_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_tracking_usage');
    }
};
