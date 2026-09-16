<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A physical biometric terminal (SBXPC family) known to a tenant. The Windows
 * bridge service authenticates as an api_client with scope biometric:write and
 * names devices by serial_number in its punch batches.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('biometric_devices')) {
            return;
        }

        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('serial_number')->unique();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('ip_address')->nullable();      // LAN mode
            $table->string('p2p_uid')->nullable();         // 8-byte hex, P2P mode
            $table->string('site_timezone', 64)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('api_client_id')->nullable(); // owning bridge key
            $table->string('direction_mode', 20)->default('auto');   // auto|in|out|by_verify_mode
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_punch_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
