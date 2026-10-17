<?php

namespace App\Services\Attendance;

use App\Exceptions\PeriodLockedException;
use Illuminate\Support\Facades\DB;

/**
 * Re-grades attendance days already worked when their shift changes afterwards.
 *
 * A day's shift, late / early minutes and status are stamped when its punches
 * are rolled up. A shift assigned / changed / removed later for a past date
 * (back-dated assignment, roster edit, rotation, approved shift request) used
 * to leave those days graded with the old (often: no) shift — so a 10:17
 * clock-in on a 10:00 shift stayed Present with 0 late minutes.
 *
 * ShiftChangeRecorder::record() — which every shift write path already goes
 * through — queues each employee-day whose shift really changed (today or
 * earlier). After the response is sent, run() rebuilds those days through
 * AttendanceRollupService::recompute() (the same step a punch takes) and then
 * re-runs the late policy once per employee-month.
 *
 * Never touched: a day without active punches (nothing to rebuild), a
 * hand-marked day (the admin's mark stands) and a payroll-locked month.
 */
class ShiftChangeRegrader
{
    /** "tenant|user|date" => [tenantId, userId, date] */
    private array $pending = [];

    private bool $scheduled = false;

    public function queue(int $tenantId, int $userId, string $date): void
    {
        $date = substr($date, 0, 10);
        if ($date > now()->toDateString()) {
            return;   // nothing worked yet
        }

        $this->pending["$tenantId|$userId|$date"] = [$tenantId, $userId, $date];
        if (! $this->scheduled) {
            $this->scheduled = true;
            // After the response (web / API) or at the end of the command — the
            // admin never waits for it, and it needs no queue worker.
            app()->terminating(fn () => $this->run());
        }
    }

    /** @return array{regraded:int, skipped:int, locked:int, failed:int} */
    public function run(): array
    {
        $days = $this->pending;
        $this->pending = [];
        $this->scheduled = false;
        $counts = ['regraded' => 0, 'skipped' => 0, 'locked' => 0, 'failed' => 0];
        if ($days === []) {
            return $counts;
        }

        // One entry-service instance for the rollup, so its month refresh can be deferred.
        $entry = app(AttendanceEntryService::class);
        $rollup = app()->make(AttendanceRollupService::class, ['entry' => $entry]);
        $ctx = new AuditContext(source: 'policy_recalc', reason: 'Shift changed for a past day — re-graded with the new shift');

        $entry->withDeferredMonthRefresh(function () use ($days, $rollup, $ctx, &$counts) {
            foreach ($days as [$tenantId, $userId, $date]) {
                if (! $this->regradable($tenantId, $userId, $date)) {
                    $counts['skipped']++;

                    continue;
                }
                try {
                    $rollup->recompute($userId, $tenantId, $date, $ctx);
                    $counts['regraded']++;
                } catch (PeriodLockedException) {
                    $counts['locked']++;
                } catch (\Throwable $e) {
                    report($e);
                    $counts['failed']++;
                }
            }
        });

        return $counts;
    }

    /** Has active punches to rebuild from, and was not set by hand. */
    private function regradable(int $tenantId, int $userId, string $date): bool
    {
        $row = DB::table('attendances')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->first(['attendance_type', 'marked_by']);
        if ($row && ($row->attendance_type === 'manual' || ! empty($row->marked_by))) {
            return false;
        }

        return DB::table('attendance_punches')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('date', $date)->where('status', 'active')->exists();
    }
}
