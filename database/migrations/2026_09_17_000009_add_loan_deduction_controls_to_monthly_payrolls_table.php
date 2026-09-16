<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('monthly_payrolls')) {
            return;
        }

        if (! Schema::hasColumn('monthly_payrolls', 'loan_deduction_enabled')) {
            Schema::table('monthly_payrolls', function (Blueprint $table) {
                $table->boolean('loan_deduction_enabled')->default(true)->after('loan_deduction');
            });
        }

        if (! Schema::hasColumn('monthly_payrolls', 'loan_deduction_computed')) {
            Schema::table('monthly_payrolls', function (Blueprint $table) {
                $table->decimal('loan_deduction_computed', 15, 2)->nullable()->after('loan_deduction_enabled');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('monthly_payrolls')) {
            return;
        }

        if (Schema::hasColumn('monthly_payrolls', 'loan_deduction_computed')) {
            Schema::table('monthly_payrolls', function (Blueprint $table) {
                $table->dropColumn('loan_deduction_computed');
            });
        }

        if (Schema::hasColumn('monthly_payrolls', 'loan_deduction_enabled')) {
            Schema::table('monthly_payrolls', function (Blueprint $table) {
                $table->dropColumn('loan_deduction_enabled');
            });
        }
    }
};
