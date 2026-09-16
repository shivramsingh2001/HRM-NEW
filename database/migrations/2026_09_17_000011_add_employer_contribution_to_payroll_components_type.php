<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MonthlyPayrollController::savePayrollComponents() already writes
 * component_type = 'employer_contribution' for employer PF/ESI line items,
 * but the enum only ever allowed 'earning'/'deduction' -- with no
 * STRICT_TRANS_TABLES on this connection, MySQL silently truncated those
 * inserts to '' instead of rejecting them. This just makes the schema match
 * what the application code already intends to write.
 */
return new class extends Migration
{
    private string $withNew = "'earning','deduction','employer_contribution'";

    private string $previous = "'earning','deduction'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payroll_components MODIFY COLUMN component_type ENUM({$this->withNew}) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Collapse the new value before shrinking the enum -- these rows were
        // never valid deductions/earnings, but 'deduction' keeps them out of
        // earnings totals rather than silently re-corrupting them to ''.
        DB::table('payroll_components')
            ->where('component_type', 'employer_contribution')
            ->update(['component_type' => 'deduction']);

        DB::statement("ALTER TABLE payroll_components MODIFY COLUMN component_type ENUM({$this->previous}) NOT NULL");
    }
};
