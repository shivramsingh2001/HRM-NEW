<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime policy (Company Policies → Overtime): on/off switch, mode
 * (request & approval vs automatic from attendance), where automatic overtime
 * starts, minimum hours and multiplier-or-fixed rate. Automatic overtime is
 * stored as an approved overtime_requests row with source = 'auto', so
 * approvals, reports and payroll keep reading one table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_settings', 'enabled')) {
                $table->boolean('enabled')->default(true)->after('tenant_id');
            }
            if (! Schema::hasColumn('overtime_settings', 'mode')) {
                $table->enum('mode', ['request', 'auto'])->default('request')->after('enabled');
            }
            if (! Schema::hasColumn('overtime_settings', 'auto_start_basis')) {
                $table->enum('auto_start_basis', ['grace', 'fixed'])->default('grace')->after('mode');
            }
            if (! Schema::hasColumn('overtime_settings', 'auto_start_after_minutes')) {
                $table->unsignedSmallInteger('auto_start_after_minutes')->default(0)->after('auto_start_basis');
            }
            if (! Schema::hasColumn('overtime_settings', 'min_hours')) {
                $table->decimal('min_hours', 5, 2)->nullable()->after('auto_start_after_minutes');
            }
            if (! Schema::hasColumn('overtime_settings', 'rate_type')) {
                $table->enum('rate_type', ['multiplier', 'fixed'])->default('multiplier')->after('min_hours');
            }
            if (! Schema::hasColumn('overtime_settings', 'fixed_rate_per_hour')) {
                $table->decimal('fixed_rate_per_hour', 10, 2)->nullable()->after('rate_multiplier');
            }
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_requests', 'source')) {
                $table->enum('source', ['request', 'auto'])->default('request')->after('status');
            }
            if (! Schema::hasColumn('overtime_requests', 'attendance_id')) {
                $table->unsignedBigInteger('attendance_id')->nullable()->after('source');
            }
            if (! Schema::hasColumn('overtime_requests', 'auto_minutes')) {
                $table->unsignedInteger('auto_minutes')->nullable()->after('attendance_id');
            }
            // Set when HR approves/rejects/edits an automatic entry by hand: recalculation leaves it alone.
            if (! Schema::hasColumn('overtime_requests', 'manually_adjusted_at')) {
                $table->timestamp('manually_adjusted_at')->nullable()->after('auto_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            foreach (['manually_adjusted_at', 'auto_minutes', 'attendance_id', 'source'] as $c) {
                if (Schema::hasColumn('overtime_requests', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('overtime_settings', function (Blueprint $table) {
            foreach (['fixed_rate_per_hour', 'rate_type', 'min_hours', 'auto_start_after_minutes', 'auto_start_basis', 'mode', 'enabled'] as $c) {
                if (Schema::hasColumn('overtime_settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
