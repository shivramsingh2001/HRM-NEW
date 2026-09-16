<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2 / T2-B — public API credentials, request log, idempotency store.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_clients')) {
            Schema::create('api_clients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->string('key_id', 24)->unique();       // public identifier, e.g. "ak_live_ab12cd34"
                $table->string('secret_hash');                 // hash() of the secret half
                $table->json('scopes')->nullable();            // ["attendance:read", ...]
                $table->unsignedInteger('rate_limit_per_min')->default(120);
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('api_request_logs')) {
            Schema::create('api_request_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('api_client_id')->nullable()->index();
                $table->string('method', 10);
                $table->string('path', 500);
                $table->unsignedSmallInteger('status');
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('idempotency_key', 80)->nullable();
                $table->string('ip', 64)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('api_client_id');
                $table->string('key', 80);
                $table->string('request_hash', 64);
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->mediumText('response_body')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->unique(['tenant_id', 'api_client_id', 'key'], 'idem_client_key_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('api_clients');
    }
};
