<?php

namespace Tests\Feature;

use Tests\Support\ConventionScanner;
use Tests\TestCase;

/**
 * Code-quality plan, Phase 5 — the coding conventions, enforced (static scan of
 * app/Http/Controllers, no database; runs in CI). See docs/architecture.md →
 * "Coding conventions".
 *
 *  1. No swallowed generic exceptions: a `catch (Exception|Throwable …)` must
 *     log / report() / rethrow. (All 209 old ones got report($e) on
 *     2026-10-10 — zero allowed.)
 *  2. Validation belongs in a FormRequest: `Validator::make()` inside a
 *     controller may not grow; files that still have some are listed with their
 *     count and may only go down.
 *  3. No hand-written SQL in controllers (DB::select / statement / insert …):
 *     same shrink-only list. Put queries in a service / query class with an
 *     explicit tenant_id.
 */
class ConventionGuardTest extends TestCase
{
    /** file => Validator::make() calls when the guard was introduced (2026-10-10); may only go down. */
    private const VALIDATOR_MAKE_BASELINE = [
        'app/Http/Controllers/Announcement/AnnouncementController.php' => 2,
        'app/Http/Controllers/Api/Announcement/AnnouncementController.php' => 1,
        'app/Http/Controllers/Api/Attendance/AttendanceTrackController.php' => 2,
        'app/Http/Controllers/Api/Attendance/ClockController.php' => 2,
        'app/Http/Controllers/Api/Attendance/LocationTrackingController.php' => 1,
        'app/Http/Controllers/Api/Attendance/MobileRegularizationController.php' => 2,
        'app/Http/Controllers/Api/Attendance/OvertimeController.php' => 4,
        'app/Http/Controllers/Api/Attendance/RequestController.php' => 4,
        'app/Http/Controllers/Api/Auth/AuthController.php' => 6,
        'app/Http/Controllers/Api/Expense/ExpenseController.php' => 1,
        'app/Http/Controllers/Api/FCM/FCMController.php' => 2,
        'app/Http/Controllers/Api/Leave/LeaveController.php' => 2,
        'app/Http/Controllers/Api/Loan/LoanController.php' => 2,
        'app/Http/Controllers/Api/Mom/MeetingController.php' => 4,
        'app/Http/Controllers/Api/Offboarding/offboardingController.php' => 1,
        'app/Http/Controllers/Api/Shift/ShiftRequestController.php' => 1,
        'app/Http/Controllers/Api/Task/TaskController.php' => 3,
        'app/Http/Controllers/Api/User/UserController.php' => 4,
        'app/Http/Controllers/Attendance/AttendanceContoller.php' => 1,
        'app/Http/Controllers/Attendance/AttendanceRegularizationController.php' => 4,
        'app/Http/Controllers/Attendance/OvertimeController.php' => 6,
        'app/Http/Controllers/AttendanceLocation/AttendanceLocationController.php' => 2,
        'app/Http/Controllers/Auth/Announcement/AnnouncementController.php' => 2,
        'app/Http/Controllers/Expense/ExpenseBudgetController.php' => 1,
        'app/Http/Controllers/Expense/ExpenseController.php' => 2,
        'app/Http/Controllers/Expense/PaymentBatchController.php' => 3,
        'app/Http/Controllers/Expense/PaymentController.php' => 3,
        'app/Http/Controllers/Leave/LeaveController.php' => 2,
        'app/Http/Controllers/Loan/LoanCategoryController.php' => 3,
        'app/Http/Controllers/Loan/LoanController.php' => 7,
        'app/Http/Controllers/Mom/MeetingMinuteController.php' => 1,
        'app/Http/Controllers/Payroll/MonthlyPayrollController.php' => 2,
        'app/Http/Controllers/Payroll/MonthlyPayrollStatusController.php' => 1,
        'app/Http/Controllers/Payroll/PayrollBonusController.php' => 1,
        'app/Http/Controllers/Payroll/PayrollComponentController.php' => 1,
        'app/Http/Controllers/Payroll/PayrollEmployeeStructureController.php' => 1,
        'app/Http/Controllers/Payroll/PayrollStructureController.php' => 1,
        'app/Http/Controllers/Project/ProjectController.php' => 2,
        'app/Http/Controllers/Recruitment/JobOpeningController.php' => 2,
        'app/Http/Controllers/Shift/RosterController.php' => 6,
        'app/Http/Controllers/Shift/ShiftAssignmentController.php' => 6,
        'app/Http/Controllers/Task/TaskController.php' => 4,
    ];

    /** file => raw DB::… calls when the guard was introduced; may only go down. */
    private const RAW_SQL_BASELINE = [
        'app/Http/Controllers/AI/AttendanceController.php' => 1,
        'app/Http/Controllers/Api/Attendance/AttendanceController.php' => 1,
        'app/Http/Controllers/Api/User/UserController.php' => 2,
        'app/Http/Controllers/Attendance/AttendanceContoller.php' => 2,
    ];

    public function test_no_controller_swallows_a_generic_exception(): void
    {
        $offenders = [];
        foreach (ConventionScanner::controllers() as $file => $code) {
            if ($n = ConventionScanner::genericSwallowedCatches($code)) {
                $offenders[] = "{$file}: {$n}";
            }
        }

        $this->assertSame([], $offenders, "catch (Exception/Throwable) that neither logs, report()s nor rethrows — add report(\$e) (the response can stay the same):\n  ".implode("\n  ", $offenders));
    }

    public function test_validator_make_in_controllers_only_shrinks(): void
    {
        $this->assertShrinkOnly('validatorMakes', self::VALIDATOR_MAKE_BASELINE, 'Validator::make() in a controller — use a FormRequest (app/Http/Requests) instead');
    }

    public function test_raw_sql_in_controllers_only_shrinks(): void
    {
        $this->assertShrinkOnly('rawSql', self::RAW_SQL_BASELINE, 'Hand-written SQL (DB::select/statement/insert/…) in a controller — move it to a service / query class with an explicit tenant_id');
    }

    private function assertShrinkOnly(string $metric, array $baseline, string $message): void
    {
        $over = [];
        $stale = [];
        foreach (ConventionScanner::controllers() as $file => $code) {
            $now = ConventionScanner::{$metric}($code);
            $allowed = $baseline[$file] ?? 0;
            if ($now > $allowed) {
                $over[] = "{$file}: {$now} (allowed {$allowed})";
            } elseif ($now < $allowed) {
                $stale[] = "'{$file}' => {$now},";
            }
        }
        $this->assertSame([], $over, "{$message}:\n  ".implode("\n  ", $over));
        $this->assertSame([], $stale, "Fewer than recorded — lower the baseline in ConventionGuardTest to:\n  ".implode("\n  ", $stale));
    }
}
