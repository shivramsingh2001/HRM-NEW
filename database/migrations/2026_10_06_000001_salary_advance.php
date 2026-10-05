<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary Advance — a second type inside Loans & Advances. An advance is a
 * `loans` row with loan_kind = 'salary_advance' and advance_month = 'YYYY-MM'
 * and ONE repayment row for that month, recovered by that month's payroll as
 * its own payslip line (monthly_payrolls.salary_advance_deduction). Existing
 * rows default to 'loan' — nothing changes for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'loan_kind')) {
                $table->enum('loan_kind', ['loan', 'salary_advance'])->default('loan')->after('loan_type_id');
            }
            if (! Schema::hasColumn('loans', 'advance_month')) {
                $table->char('advance_month', 7)->nullable()->after('loan_kind');
            }
        });
        if (! $this->hasIndex('loans', 'loans_kind_month_idx')) {
            Schema::table('loans', fn (Blueprint $t) => $t->index(['tenant_id', 'user_id', 'loan_kind', 'advance_month'], 'loans_kind_month_idx'));
        }

        Schema::table('loan_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_categories', 'kind')) {
                $table->enum('kind', ['loan', 'salary_advance'])->default('loan')->after('code');
            }
            if (! Schema::hasColumn('loan_categories', 'max_percent_of_gross')) {
                $table->decimal('max_percent_of_gross', 5, 2)->nullable()->after('max_amount');
            }
        });

        Schema::table('monthly_payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_payrolls', 'salary_advance_deduction')) {
                $table->decimal('salary_advance_deduction', 12, 2)->default(0)->after('loan_deduction_computed');
            }
            if (! Schema::hasColumn('monthly_payrolls', 'salary_advance_deduction_computed')) {
                $table->decimal('salary_advance_deduction_computed', 12, 2)->nullable()->after('salary_advance_deduction');
            }
        });
    }

    public function down(): void
    {
        if ($this->hasIndex('loans', 'loans_kind_month_idx')) {
            Schema::table('loans', fn (Blueprint $t) => $t->dropIndex('loans_kind_month_idx'));
        }
        foreach (['loans' => ['loan_kind', 'advance_month'], 'loan_categories' => ['kind', 'max_percent_of_gross'],
            'monthly_payrolls' => ['salary_advance_deduction', 'salary_advance_deduction_computed']] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}`"))->contains('Key_name', $name);
    }
};
