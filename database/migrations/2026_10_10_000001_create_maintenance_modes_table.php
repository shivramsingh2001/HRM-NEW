<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide maintenance mode (single row). Switched on/off from the
 * Super Admin Panel; read by GET /api/maintenance and the CheckMaintenanceMode
 * middleware. `enabled_by` is the super admin who last changed it, so it
 * points at super_admins (not users — panel staff are not HRM users).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_modes')) {
            Schema::create('maintenance_modes', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_enabled')->default(false)->index();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->timestamp('start_time')->nullable();
                $table->timestamp('end_time')->nullable();
                $table->json('allowed_ips')->nullable();
                $table->json('allowed_users')->nullable();
                $table->unsignedBigInteger('enabled_by')->nullable()->index('maintenance_modes_enabled_by_foreign');
                $table->timestamps();

                if (Schema::hasTable('super_admins')) {
                    $table->foreign('enabled_by', 'maintenance_modes_enabled_by_foreign')
                        ->references('id')->on('super_admins')
                        ->nullOnDelete()->cascadeOnUpdate();
                }
            });
        }

        if (! DB::table('maintenance_modes')->exists()) {
            DB::table('maintenance_modes')->insert([
                'id' => 1,
                'is_enabled' => false,
                'title' => 'Under Maintenance',
                'message' => 'We are currently performing scheduled maintenance. Please check back soon.',
                'allowed_ips' => '[]',
                'allowed_users' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_modes');
    }
};
