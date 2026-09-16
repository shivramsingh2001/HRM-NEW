<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 1 / W5 — audit trail for tenant data export and erasure.
 *
 * tenant_exports gates `tenant:purge --hard` (a recent export must exist).
 * tenant_purges is a tombstone: it is never deleted, even by a hard purge, so
 * there is always a record that a tenant's data was removed and by whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_exports')) {
            Schema::create('tenant_exports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('path', 1000);
                $table->json('row_counts')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('tenant_purges')) {
            Schema::create('tenant_purges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('tenant_uuid', 64)->nullable();
                $table->string('mode', 20); // soft | hard
                $table->json('counts')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('reason', 500)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_exports');
        Schema::dropIfExists('tenant_purges');
    }
};
