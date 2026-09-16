<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2 / T2-C — outbound webhooks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('url', 1000);
                $table->string('secret', 80);
                $table->json('events');                 // ["attendance.marked", ...] or ["*"]
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestamp('disabled_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('webhook_endpoint_id')->index();
                $table->string('event', 60);
                $table->json('payload');
                $table->unsignedTinyInteger('attempt')->default(0);
                $table->string('status', 12)->default('pending'); // pending|success|failed|dead
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->unsignedInteger('response_ms')->nullable();
                $table->timestamp('next_retry_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->index(['status', 'next_retry_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
