<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company Policies → Overtime limits & rate → "Additional shift pay": hours
 * worked in an assigned 2nd+ shift are paid either as overtime hours (the
 * existing behaviour, default) or as extra working days at one day's salary.
 *
 *  - overtime_settings.extra_shift_pay_mode   overtime | extra_day
 *  - monthly_payrolls + extra_work_days / extra_work_day_rate / extra_work_day_amount
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_settings', 'extra_shift_pay_mode')) {
                $table->enum('extra_shift_pay_mode', ['overtime', 'extra_day'])->default('overtime')->after('fixed_rate_per_hour');
            }
        });

        Schema::table('monthly_payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_payrolls', 'extra_work_days')) {
                $table->decimal('extra_work_days', 6, 2)->default(0)->after('shift_allowance_days');
            }
            if (! Schema::hasColumn('monthly_payrolls', 'extra_work_day_rate')) {
                $table->decimal('extra_work_day_rate', 12, 2)->default(0)->after('extra_work_days');
            }
            if (! Schema::hasColumn('monthly_payrolls', 'extra_work_day_amount')) {
                $table->decimal('extra_work_day_amount', 12, 2)->default(0)->after('extra_work_day_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_payrolls', function (Blueprint $table) {
            foreach (['extra_work_day_amount', 'extra_work_day_rate', 'extra_work_days'] as $c) {
                if (Schema::hasColumn('monthly_payrolls', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('overtime_settings', function (Blueprint $table) {
            if (Schema::hasColumn('overtime_settings', 'extra_shift_pay_mode')) {
                $table->dropColumn('extra_shift_pay_mode');
            }
        });
    }
};
