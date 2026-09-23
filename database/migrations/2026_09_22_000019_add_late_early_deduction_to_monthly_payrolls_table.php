<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persists the Late Arrival / Early Leaving payroll deduction amounts
 * computed by App\Services\Payroll\LateEarlyDeductionCalculator, for both
 * the legacy engine (MonthlyPayrollController) and the dynamic engine
 * (PayrollCalculationEngine) — same shape as the existing loan_deduction
 * column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_payrolls', 'late_deduction')) {
                $table->decimal('late_deduction', 15, 2)->default(0)->after('loan_deduction_computed');
            }
            if (! Schema::hasColumn('monthly_payrolls', 'early_deduction')) {
                $table->decimal('early_deduction', 15, 2)->default(0)->after('late_deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_payrolls', function (Blueprint $table) {
            $table->dropColumn(['late_deduction', 'early_deduction']);
        });
    }
};
