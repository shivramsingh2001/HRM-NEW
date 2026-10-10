<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Code-quality plan, Phase 0 — size budget for controllers.
 *
 * A controller may be at most MAX_LINES long and none of its methods longer
 * than MAX_METHOD_LINES. Controllers that were already over budget when the
 * guard was introduced are listed in BASELINE with their size at that time;
 * they may only shrink. When one shrinks, lower its number here (the test
 * tells you), and remove it once it fits the budget.
 *
 * Static file scan — no database needed (runs in CI).
 */
class ControllerSizeGuardTest extends TestCase
{
    private const MAX_LINES = 600;

    private const MAX_METHOD_LINES = 80;

    /** file => [max lines, max method lines] recorded on 2026-10-10 (or after a refactor lowered them). */
    private const BASELINE = [
        'app/Http/Controllers/AI/AnnoucementController.php' => [127, 112],
        'app/Http/Controllers/AI/AssetController.php' => [151, 129],
        'app/Http/Controllers/AI/AttendanceRegularizationController.php' => [130, 115],
        'app/Http/Controllers/AI/DailyReportController.php' => [122, 98],
        'app/Http/Controllers/AI/ExpenseController.php' => [143, 127],
        'app/Http/Controllers/AI/LeaveController.php' => [348, 296],
        'app/Http/Controllers/AI/LeaveHistoryController.php' => [132, 106],
        'app/Http/Controllers/AI/LoanController.php' => [136, 116],
        'app/Http/Controllers/AI/MeetingController.php' => [119, 96],
        'app/Http/Controllers/AI/OffboardingController.php' => [138, 117],
        'app/Http/Controllers/AI/OnboardingController.php' => [136, 113],
        'app/Http/Controllers/AI/OvertimeController.php' => [137, 112],
        'app/Http/Controllers/AI/PayrollController.php' => [158, 130],
        'app/Http/Controllers/AI/PerformanceController.php' => [173, 151],
        'app/Http/Controllers/AI/PolicyController.php' => [166, 137],
        'app/Http/Controllers/AI/ProfileController.php' => [174, 157],
        'app/Http/Controllers/AI/ProjectController.php' => [253, 184],
        'app/Http/Controllers/AI/RecruitmentController.php' => [183, 158],
        'app/Http/Controllers/AI/RequestController.php' => [242, 225],
        'app/Http/Controllers/AI/ShiftController.php' => [470, 114],
        'app/Http/Controllers/AI/TaskController.php' => [395, 121],
        'app/Http/Controllers/AI/TeamController.php' => [234, 217],
        'app/Http/Controllers/Api/Attendance/AttendanceController.php' => [491, 188],
        'app/Http/Controllers/Api/Attendance/ClockController.php' => [867, 362],
        'app/Http/Controllers/Api/Attendance/MobileRegularizationController.php' => [542, 155],
        'app/Http/Controllers/Api/Attendance/OvertimeController.php' => [710, 132],
        'app/Http/Controllers/Api/Attendance/RequestController.php' => [785, 149],
        'app/Http/Controllers/Api/Auth/AuthController.php' => [706, 114],
        'app/Http/Controllers/Api/Expense/ExpenseController.php' => [408, 95],
        'app/Http/Controllers/Api/Leave/LeaveController.php' => [516, 131],
        'app/Http/Controllers/Api/Loan/LoanController.php' => [710, 165],
        'app/Http/Controllers/Api/Mom/MeetingController.php' => [856, 157],
        'app/Http/Controllers/Api/Offboarding/offboardingController.php' => [555, 83],
        'app/Http/Controllers/Api/Payroll/PayrollController.php' => [451, 100],
        'app/Http/Controllers/Api/Shift/ShiftController.php' => [389, 114],
        'app/Http/Controllers/Api/Task/TaskController.php' => [1072, 245],
        'app/Http/Controllers/Api/User/UserController.php' => [1384, 502],
        'app/Http/Controllers/Attendance/AttendanceContoller.php' => [824, 283],
        'app/Http/Controllers/Attendance/AttendanceRegularizationController.php' => [963, 123],
        'app/Http/Controllers/Attendance/OvertimeController.php' => [889, 160],
        'app/Http/Controllers/Attendance/RequestController.php' => [1059, 112],
        'app/Http/Controllers/Auth/AuthController.php' => [176, 116],
        'app/Http/Controllers/Concerns/TeamAttendanceStatus.php' => [309, 117],
        'app/Http/Controllers/Expense/ExpenseController.php' => [790, 149],
        'app/Http/Controllers/Expense/PaymentController.php' => [496, 107],
        'app/Http/Controllers/Leave/LeaveController.php' => [754, 153],
        'app/Http/Controllers/Leave/LeaveCreditController.php' => [782, 79],
        'app/Http/Controllers/Loan/LoanController.php' => [1414, 108],
        'app/Http/Controllers/Mom/MeetingController.php' => [737, 145],
        'app/Http/Controllers/Mom/MeetingMinuteController.php' => [273, 156],
        'app/Http/Controllers/Payroll/PayrollEmployeeStructureController.php' => [441, 211],
        'app/Http/Controllers/Performance/ManagerPerformanceReviewController.php' => [497, 108],
        'app/Http/Controllers/Performance/PerformanceController.php' => [438, 160],
        'app/Http/Controllers/Project/ProjectController.php' => [872, 155],
        'app/Http/Controllers/Recruitment/JobOpeningController.php' => [879, 71],
        'app/Http/Controllers/Recruitment/RecruitmentController.php' => [285, 145],
        'app/Http/Controllers/Report/DayAttendanceReportController.php' => [777, 388],
        'app/Http/Controllers/Report/DetailAttendanceReportController.php' => [792, 382],
        'app/Http/Controllers/Report/EmployeeWiseAttendanceReportController.php' => [540, 460],
        'app/Http/Controllers/Report/HourlyAttendanceReportController.php' => [552, 292],
        'app/Http/Controllers/Report/LeaveReportController.php' => [390, 98],
        'app/Http/Controllers/Report/LocationAttendanceReportController.php' => [623, 201],
        'app/Http/Controllers/Report/OverallAttendanceReportController.php' => [557, 277],
        'app/Http/Controllers/Report/OvertimeReportController.php' => [210, 178],
        'app/Http/Controllers/Report/PunchReportController.php' => [256, 82],
        'app/Http/Controllers/Report/ShiftReportController.php' => [302, 156],
        'app/Http/Controllers/Report/TaskReportController.php' => [1234, 187],
        'app/Http/Controllers/Shift/RosterController.php' => [686, 148],
        'app/Http/Controllers/Shift/ShiftAssignmentController.php' => [907, 193],
        'app/Http/Controllers/Task/TaskController.php' => [1590, 172],
        'app/Http/Controllers/Team/AttendanceSummaryController.php' => [603, 206],
        'app/Http/Controllers/Team/TeamController.php' => [330, 137],
        'app/Http/Controllers/Team/TeamMemberController.php' => [574, 151],
        'app/Http/Controllers/User/EmployeeEditController.php' => [621, 170],
        'app/Http/Controllers/User/EmployeeProfileActionController.php' => [597, 88],
        'app/Http/Controllers/User/UserController.php' => [306, 196],
    ];

    public function test_controllers_stay_within_their_size_budget(): void
    {
        $problems = [];
        $stale = [];

        foreach ($this->measure() as $file => [$lines, $longest]) {
            [$maxLines, $maxMethod] = self::BASELINE[$file] ?? [self::MAX_LINES, self::MAX_METHOD_LINES];
            $maxLines = max($maxLines, self::MAX_LINES);
            $maxMethod = max($maxMethod, self::MAX_METHOD_LINES);

            if ($lines > $maxLines) {
                $problems[] = "{$file}: {$lines} lines (budget {$maxLines})";
            }
            if ($longest > $maxMethod) {
                $problems[] = "{$file}: a method of {$longest} lines (budget {$maxMethod})";
            }
            if (isset(self::BASELINE[$file]) && ($lines < self::BASELINE[$file][0] || $longest < self::BASELINE[$file][1])) {
                $stale[] = "'{$file}' => [{$lines}, {$longest}],";
            }
        }

        $this->assertSame([], $problems, "Controller over its size budget — split it into services / smaller controllers:\n  ".implode("\n  ", $problems));
        $this->assertSame([], $stale, "A baselined controller got smaller — lower ControllerSizeGuardTest::BASELINE to:\n  ".implode("\n  ", $stale));
    }

    /** @return array<string, array{0:int,1:int}> file => [lines, longest method] */
    private function measure(): array
    {
        $root = dirname(__DIR__, 2);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/app/Http/Controllers', \FilesystemIterator::SKIP_DOTS));
        $out = [];
        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }
            // Repo-relative with forward slashes on every OS (Windows mixes \ and /).
            $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', $root)) + 1);
            $code = file_get_contents($f->getPathname());
            $out[$rel] = [substr_count($code, "\n") + 1, $this->longestMethod($code)];
        }
        ksort($out);

        return $out;
    }

    /** Longest function body in lines, by brace matching from each `function` keyword. */
    private function longestMethod(string $code): int
    {
        $tokens = token_get_all($code);
        $longest = 0;
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $startLine = $tokens[$i][2];
            // Find the body's opening brace (skip abstract / interface signatures ending in ';').
            for ($j = $i + 1; $j < $n && $tokens[$j] !== '{' && $tokens[$j] !== ';'; $j++);
            if ($j >= $n || $tokens[$j] === ';') {
                continue;
            }
            $depth = 0;
            $endLine = $startLine;
            for ($k = $j; $k < $n; $k++) {
                $t = $tokens[$k];
                if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($t === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                if (is_array($t)) {
                    $endLine = $t[2] + substr_count($t[1], "\n");
                }
            }
            $longest = max($longest, $endLine - $startLine + 1);
            // Closures inside a method are measured as part of it; don't restart inside them.
            $i = $k;
        }

        return $longest;
    }
}
