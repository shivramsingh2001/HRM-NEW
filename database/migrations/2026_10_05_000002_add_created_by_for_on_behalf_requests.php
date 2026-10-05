<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests raised by admin / HR on behalf of an employee (Loan, Overtime,
 * Regularization, Expense "Create … request" buttons). created_by = who raised
 * the row; NULL (every existing row) or = user_id means the employee raised it
 * themselves. leaves already has applied_by + source = 'on_behalf'.
 */
return new class extends Migration
{
    private const TABLES = ['loans', 'overtime_requests', 'attendance_regularizations', 'expenses'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'created_by')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('created_by')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'created_by')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('created_by');
                });
            }
        }
    }
};
