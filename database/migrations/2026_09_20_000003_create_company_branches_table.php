<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real "Company Branch" — a pure organizational profile (name, address,
 * contact, manager) for companies with multiple physical offices. Separate
 * from `attendance_locations` (geofencing) on purpose: one employee belongs
 * to at most one Branch, and Branch has no lat/long/radius at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_branches')) {
            Schema::create('company_branches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->text('address')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email', 150)->nullable();
                $table->unsignedBigInteger('branch_head')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index('tenant_id');
                $table->foreign('branch_head')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_branches');
    }
};
