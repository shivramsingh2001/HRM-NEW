<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dynamic-engine-only employees (created after a tenant's cutover, once
     * the legacy Payroll Masters/Employee Payroll screens are removed) never
     * get a user_payrolls row. employee_payroll_id was NOT NULL, so
     * generating their first payslip would violate the column constraint.
     * The FK to user_payrolls stays -- only the NOT NULL is dropped.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE monthly_payrolls DROP FOREIGN KEY monthly_payrolls_employee_payroll_id_foreign');
        DB::statement('ALTER TABLE monthly_payrolls MODIFY COLUMN employee_payroll_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE monthly_payrolls ADD CONSTRAINT monthly_payrolls_employee_payroll_id_foreign FOREIGN KEY (employee_payroll_id) REFERENCES user_payrolls (id)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE monthly_payrolls DROP FOREIGN KEY monthly_payrolls_employee_payroll_id_foreign');
        DB::statement('ALTER TABLE monthly_payrolls MODIFY COLUMN employee_payroll_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE monthly_payrolls ADD CONSTRAINT monthly_payrolls_employee_payroll_id_foreign FOREIGN KEY (employee_payroll_id) REFERENCES user_payrolls (id)');
    }
};
