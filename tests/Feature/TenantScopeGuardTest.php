<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tier 1 / W5a — static guard against cross-tenant data leaks.
 *
 * The multi-tenant global scope (App\Traits\TenantTrait) is the only thing
 * keeping one tenant's attendance data out of another's queries. Every
 * `withoutGlobalScope(s)` call on a tenant-scoped model must therefore either
 * re-apply an explicit tenant / primary-key filter in the same statement, or
 * live in a file on the reviewed allow-list (CLI sweeps that filter per row,
 * the pre-tenant-context login path).
 *
 * This test fails CI when a new unguarded bypass is introduced.
 */
class TenantScopeGuardTest extends TestCase
{
    /**
     * Files where a bare global sweep is reviewed and intentional. Adding a file
     * here is a deliberate act — it means a human confirmed every
     * withoutGlobalScope() in it is either CLI-wide by design (and filters per
     * row) or re-scopes by an explicit tenant_id further down the same builder.
     */
    private const ALLOWED_FILES = [
        'app/Traits/TenantTrait.php',                         // defines the scope + allTenants()
        'app/Console/Commands/UpdateAttendanceSummaries.php',  // CLI: spans tenants, re-scopes per user
        'app/Console/Commands/AutoClockOutCommand.php',        // CLI: sweeps open rows, filters per row
        'app/Console/Commands/CheckMissedCheckIns.php',        // CLI: hydrates notifiables by id list
        'app/Console/Commands/RepairRunawayClockOuts.php',     // CLI: one-off data repair across tenants, re-grades per row's tenant
        'app/Console/Commands/ExpireShiftRequests.php',        // CLI: sweeps every company's due requests, each row keeps its tenant_id
        'app/Http/Controllers/Auth/AuthController.php',        // login runs before tenant context exists
        'app/Http/Middleware/TenantMiddleware.php',            // resolves the tenant itself (forgot/reset email → tenant)
        'app/Services/AttendanceSummaryService.php',           // every query carries an explicit tenant_id / scoped id list
        'app/Http/Controllers/AI/AttendanceRegularizationController.php', // applies ->where('ar.tenant_id', ...) before ->get()
    ];

    /**
     * Known debt (code-quality plan, 2026-10-10): unguarded bypasses that
     * existed when CI was introduced, as file => how many. A file may never
     * have MORE than its count (a new leak still fails), and the number must be
     * lowered here whenever one is fixed. Remove a file once it reaches 0.
     */
    private const KNOWN_DEBT = [
        'app/Services/Attendance/OvertimePolicyService.php' => 1,
        'app/Services/LeaveService.php' => 2,
        'app/Services/Loan/SalaryAdvanceService.php' => 2,
        'app/Services/Offboarding/OffboardingService.php' => 1,
        'app/Services/Payroll/PayrollArrearsCalculator.php' => 1,
    ];

    /** Tokens that prove a statement re-scoped itself. */
    private const SCOPE_TOKENS = [
        'tenant_id', 'tenantId', 'whereKey(', '->find(', "whereIn('id'", 'whereIn("id"',
        'where(\'id\'', 'where("id"', 'current_tenant',
    ];

    public function test_every_global_scope_bypass_is_tenant_filtered_or_allow_listed(): void
    {
        $appDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app';
        $offenders = [];

        foreach ($this->phpFiles($appDir) as $file) {
            $rel = str_replace([dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file);
            if (in_array($rel, self::ALLOWED_FILES, true)) {
                continue;
            }

            $code = file_get_contents($file);
            if (! str_contains($code, 'withoutGlobalScope')) {
                continue;
            }

            foreach ($this->bypassStatements($code) as $lineNo => $window) {
                $hasScope = false;
                foreach (self::SCOPE_TOKENS as $token) {
                    if (str_contains($window, $token)) {
                        $hasScope = true;
                        break;
                    }
                }
                if (! $hasScope) {
                    $offenders[] = "{$rel}:{$lineNo}";
                }
            }
        }

        // Known debt: allowed up to its recorded count per file; fixing one means lowering the count.
        $byFile = [];
        foreach ($offenders as $o) {
            $byFile[explode(':', $o)[0]][] = $o;
        }
        $stale = [];
        foreach (self::KNOWN_DEBT as $file => $max) {
            $found = count($byFile[$file] ?? []);
            if ($found <= $max) {
                $offenders = array_values(array_diff($offenders, $byFile[$file] ?? []));
            }
            if ($found < $max) {
                $stale[] = "{$file}: {$found} left, KNOWN_DEBT says {$max} — lower it";
            }
        }
        $this->assertSame([], $stale, "TenantScopeGuardTest::KNOWN_DEBT is out of date:\n  " . implode("\n  ", $stale));

        $this->assertSame(
            [],
            $offenders,
            "Unguarded withoutGlobalScope() — add an explicit tenant_id filter, or "
            . "add the file to TenantScopeGuardTest::ALLOWED_FILES after review:\n  "
            . implode("\n  ", $offenders)
        );
    }

    /**
     * @return array<int,string>  [1-indexed line => a code window around each
     *                             bypass — from the match to the end of its
     *                             query chain, or 2000 chars, whichever comes
     *                             first. Wide enough to see a tenant filter
     *                             applied to a query variable a few statements
     *                             later; narrow enough that an unrelated
     *                             tenant_id far below does not mask a leak.]
     */
    private function bypassStatements(string $code): array
    {
        $out = [];
        $offset = 0;
        $terminators = ['->get(', '->paginate(', '->first(', '->firstOrFail(', '->pluck(',
            '->count(', '->exists(', '->cursor(', '->chunk(', '->each(', '->value('];

        while (($pos = strpos($code, 'withoutGlobalScope', $offset)) !== false) {
            $line = substr_count(substr($code, 0, $pos), "\n") + 1;
            $slice = substr($code, $pos, 2000);

            // Trim to the first query terminator so a later, unrelated query in
            // the same method cannot vouch for this one.
            $cut = strlen($slice);
            foreach ($terminators as $t) {
                $tp = strpos($slice, $t);
                if ($tp !== false && $tp + strlen($t) < $cut) {
                    $cut = $tp + strlen($t);
                }
            }

            $out[$line] = substr($slice, 0, $cut);
            $offset = $pos + strlen('withoutGlobalScope');
        }

        return $out;
    }

    /**
     * @return \Generator<string>
     */
    private function phpFiles(string $dir): \Generator
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
