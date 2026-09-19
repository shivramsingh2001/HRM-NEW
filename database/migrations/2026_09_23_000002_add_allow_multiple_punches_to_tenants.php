<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant "multiple punches per day" toggle.
 *
 *   allow_multiple_punches = 0 (default) -> exactly one Clock In + one Clock
 *                                           Out per employee per day (current
 *                                           behaviour, unchanged).
 *   allow_multiple_punches = 1           -> an employee may clock in/out
 *                                           multiple times per day; breaks are
 *                                           the idle gap between sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'allow_multiple_punches')) {
                $table->boolean('allow_multiple_punches')->default(false)->after('custom_shifts_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'allow_multiple_punches')) {
                $table->dropColumn('allow_multiple_punches');
            }
        });
    }
};
