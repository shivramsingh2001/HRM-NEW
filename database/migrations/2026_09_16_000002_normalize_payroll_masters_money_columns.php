<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Payroll Audit Phase 4 — L1. Every other monetary column in this schema
     * (user_payrolls, monthly_payrolls) is decimal(15,2); payroll_masters
     * used double, a latent source of floating-point rounding drift.
     * Confirmed no fractional-cent values exist in live data, so this is a
     * pure type normalization with no value change. Raw SQL (not
     * Schema::table()->change()) since doctrine/dbal isn't installed in this
     * project -- same approach the existing enum migrations already use.
     */
    private const COLUMNS = [
        'hra', 'conveyence', 'medical_allowance', 'children_allowance',
        'post_allowance', 'leave_travel_allowance', 'monthly_incentive',
        'provident_fund', 'employer_provident_fund', 'esi', 'employer_esi', 'pt',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $columns = implode(', ', array_map(
            fn ($c) => "MODIFY COLUMN `{$c}` DECIMAL(15,2) NOT NULL DEFAULT 0",
            self::COLUMNS
        ));

        DB::statement("ALTER TABLE payroll_masters {$columns}");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $columns = implode(', ', array_map(
            fn ($c) => "MODIFY COLUMN `{$c}` DOUBLE NOT NULL DEFAULT 0",
            self::COLUMNS
        ));

        DB::statement("ALTER TABLE payroll_masters {$columns}");
    }
};
