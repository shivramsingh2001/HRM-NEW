<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secret path segment for the FkWeb direct-push receiver
 * (POST /api/v1/biometric/fkweb/{push_token}). The terminal has no way to send
 * an auth header, so the URL itself is the credential — null = push disabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('biometric_devices', 'push_token')) {
                $table->string('push_token', 64)->nullable()->unique()->after('allow_direct_onboarding');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropUnique(['push_token']);
            $table->dropColumn('push_token');
        });
    }
};
