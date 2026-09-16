<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a terminal's enroll number (the user ID typed/enrolled on the device) to
 * an HRMS employee. `user_id` may be null while the admin has not yet mapped an
 * enroll number the bridge reported from the device.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('biometric_enrollments')) {
            return;
        }

        Schema::create('biometric_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('biometric_device_id')->index();
            $table->string('enroll_no');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name_on_device')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['biometric_device_id', 'enroll_no'], 'bio_enr_device_enroll_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_enrollments');
    }
};
