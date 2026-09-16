<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * All changes here were verified safe during Phase 0 of the payroll
     * rebuild: zero orphaned/null tenant_id values on the five tables below,
     * and zero cross-tenant payroll_code collisions in user_payrolls.
     */
    public function up(): void
    {
        // --- tenant_id: nullable unconstrained int -> not-null bigint unsigned + FK ---
        // Uses a raw ALTER (not Schema::change()) since doctrine/dbal isn't
        // installed in this project.
        foreach (['overtime_requests', 'overtime_settings', 'user_job_details', 'user_bank_details', 'designations'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$tableName}` MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL");

            $fkExists = DB::selectOne("
                SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = 'tenant_id'
                  AND REFERENCED_TABLE_NAME = 'tenants'
            ", [$tableName])->c;

            if (! $fkExists) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                });
            }
        }

        // --- payroll_components.tenant_id: add the missing FK constraint (column already exists) ---
        if (Schema::hasTable('payroll_components')) {
            $fkExists = DB::selectOne("
                SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'payroll_components'
                  AND COLUMN_NAME = 'tenant_id'
                  AND REFERENCED_TABLE_NAME = 'tenants'
            ")->c;

            if (! $fkExists) {
                Schema::table('payroll_components', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                });
            }
        }

        // --- user_payrolls.payroll_code: global unique -> tenant-scoped unique ---
        if (Schema::hasTable('user_payrolls')) {
            $uniqueExists = DB::selectOne("
                SELECT COUNT(*) AS c FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'user_payrolls'
                  AND INDEX_NAME = 'user_payrolls_payroll_code_unique'
            ")->c;

            $compositeExists = DB::selectOne("
                SELECT COUNT(*) AS c FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'user_payrolls'
                  AND INDEX_NAME = 'user_payrolls_tenant_id_payroll_code_unique'
            ")->c;

            Schema::table('user_payrolls', function (Blueprint $table) use ($uniqueExists, $compositeExists) {
                if ($uniqueExists) {
                    $table->dropUnique('user_payrolls_payroll_code_unique');
                }
                if (! $compositeExists) {
                    $table->unique(['tenant_id', 'payroll_code']);
                }
            });
        }

        // --- overtime_requests: prevent duplicate same-day submissions ---
        if (Schema::hasTable('overtime_requests')) {
            $exists = DB::selectOne("
                SELECT COUNT(*) AS c FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'overtime_requests'
                  AND INDEX_NAME = 'overtime_requests_user_id_date_unique'
            ")->c;

            if (! $exists) {
                Schema::table('overtime_requests', function (Blueprint $table) {
                    $table->unique(['user_id', 'date']);
                });
            }
        }

        // --- loan_repayments: explicit link to the payslip that actually deducted it ---
        if (Schema::hasTable('loan_repayments') && ! Schema::hasColumn('loan_repayments', 'monthly_payroll_id')) {
            Schema::table('loan_repayments', function (Blueprint $table) {
                $table->unsignedBigInteger('monthly_payroll_id')->nullable()->after('loan_id');
                $table->foreign('monthly_payroll_id')->references('id')->on('monthly_payrolls')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('loan_repayments') && Schema::hasColumn('loan_repayments', 'monthly_payroll_id')) {
            Schema::table('loan_repayments', function (Blueprint $table) {
                $table->dropForeign(['monthly_payroll_id']);
                $table->dropColumn('monthly_payroll_id');
            });
        }

        if (Schema::hasTable('overtime_requests')) {
            Schema::table('overtime_requests', function (Blueprint $table) {
                $table->dropUnique('overtime_requests_user_id_date_unique');
            });
        }

        if (Schema::hasTable('user_payrolls')) {
            Schema::table('user_payrolls', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'payroll_code']);
                $table->unique('payroll_code');
            });
        }

        if (Schema::hasTable('payroll_components')) {
            Schema::table('payroll_components', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        }

        foreach (['overtime_requests', 'overtime_settings', 'user_job_details', 'user_bank_details', 'designations'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                });
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `tenant_id` INT NULL");
            }
        }
    }
};
