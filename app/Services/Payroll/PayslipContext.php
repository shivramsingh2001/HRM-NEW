<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * Bank details + company row printed on a payslip (show page and PDFs). Was
 * the same raw `SELECT * … LIMIT 1` copied five times in
 * MonthlyPayrollController; same rows, now one query-builder implementation.
 */
class PayslipContext
{
    public function bankDetails(int $userId, ?int $tenantId): ?object
    {
        return $tenantId === null ? null : DB::table('user_bank_details')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function company(?int $tenantId): ?object
    {
        return $tenantId === null ? null : DB::table('tenants')->where('id', $tenantId)->first();
    }
}
