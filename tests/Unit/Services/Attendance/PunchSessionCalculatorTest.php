<?php

namespace Tests\Unit\Services\Attendance;

use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\PunchSessionCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class PunchSessionCalculatorTest extends TestCase
{
    private PunchSessionCalculator $sessions;
    private AttendanceCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new AttendanceCalculator();
        $this->sessions = new PunchSessionCalculator($this->calc);
    }

    private function punch(string $direction, string $punchedAt, int $id = 0): object
    {
        return (object) ['id' => $id, 'direction' => $direction, 'punched_at' => $punchedAt];
    }

    public function test_single_pair_matches_attendance_calculator_worked_seconds(): void
    {
        $in = Carbon::parse('2026-09-19 09:00:00');
        $out = Carbon::parse('2026-09-19 18:00:00');

        $paired = $this->sessions->pairSessions(new Collection([
            $this->punch('in', $in->format('Y-m-d H:i:s'), 1),
            $this->punch('out', $out->format('Y-m-d H:i:s'), 2),
        ]));

        $this->assertSame(1, $paired['session_count']);
        $this->assertFalse($paired['open_session']);
        $this->assertFalse($paired['needs_review']);
        $this->assertSame($this->calc->workedSeconds($in, $out), $paired['worked_seconds']);
        $this->assertTrue($paired['first_clock_in']->equalTo($in));
        $this->assertTrue($paired['last_clock_out']->equalTo($out));
    }

    public function test_multi_session_day_sums_worked_seconds_and_excludes_the_gap(): void
    {
        $paired = $this->sessions->pairSessions(new Collection([
            $this->punch('in', '2026-09-19 09:00:00', 1),
            $this->punch('out', '2026-09-19 13:00:00', 2), // 4h session
            $this->punch('in', '2026-09-19 14:00:00', 3),  // 1h break, not counted
            $this->punch('out', '2026-09-19 18:00:00', 4), // 4h session
        ]));

        $this->assertSame(2, $paired['session_count']);
        $this->assertFalse($paired['open_session']);
        $this->assertSame(8 * 3600, $paired['worked_seconds']);
        $this->assertSame('2026-09-19 09:00:00', $paired['first_clock_in']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-19 18:00:00', $paired['last_clock_out']->format('Y-m-d H:i:s'));
    }

    public function test_trailing_unmatched_in_is_an_open_session_not_an_error(): void
    {
        $paired = $this->sessions->pairSessions(new Collection([
            $this->punch('in', '2026-09-19 09:00:00', 1),
            $this->punch('out', '2026-09-19 13:00:00', 2),
            $this->punch('in', '2026-09-19 14:00:00', 3),
        ]));

        $this->assertTrue($paired['open_session']);
        $this->assertFalse($paired['needs_review']);
        $this->assertSame(2, $paired['session_count']);
        // clock_out is null while a session is open, even though an earlier
        // session already closed today — preserves "clock_out IS NULL means
        // still clocked in" everywhere that invariant is relied on.
        $this->assertNull($paired['last_clock_out']);
        $this->assertNull($paired['last_out_punch']);
        $this->assertSame(4 * 3600, $paired['worked_seconds']);
    }

    public function test_leading_orphan_out_is_excluded_and_flagged_never_dropped(): void
    {
        $paired = $this->sessions->pairSessions(new Collection([
            $this->punch('out', '2026-09-19 08:00:00', 1), // no prior `in` — device replay / clock skew
            $this->punch('in', '2026-09-19 09:00:00', 2),
            $this->punch('out', '2026-09-19 18:00:00', 3),
        ]));

        $this->assertTrue($paired['needs_review']);
        $this->assertCount(1, $paired['leading_orphan_outs']);
        $this->assertSame(1, $paired['leading_orphan_outs'][0]->id);
        $this->assertSame(1, $paired['session_count']);
        $this->assertSame(9 * 3600, $paired['worked_seconds']);
    }

    public function test_duplicate_in_with_no_intervening_out_orphans_the_earlier_one(): void
    {
        $paired = $this->sessions->pairSessions(new Collection([
            $this->punch('in', '2026-09-19 09:00:00', 1),
            $this->punch('in', '2026-09-19 09:05:00', 2), // fat-fingered double tap, no out between
            $this->punch('out', '2026-09-19 18:00:00', 3),
        ]));

        $this->assertTrue($paired['needs_review']);
        $this->assertCount(1, $paired['orphaned_ins']);
        $this->assertSame(1, $paired['orphaned_ins'][0]->id);
        $this->assertSame(1, $paired['session_count']);
        // The later `in` (id 2) is the one actually used as the session start.
        $this->assertSame('2026-09-19 09:05:00', $paired['first_clock_in']->format('Y-m-d H:i:s'));
    }

    public function test_overnight_session_reuses_cross_midnight_resolution(): void
    {
        $shift = (object) ['start_time' => '22:00', 'end_time' => '06:00', 'grace_minutes' => 0];

        $paired = $this->sessions->pairSessions(new Collection([
            $this->punch('in', '2026-09-19 23:50:00', 1),
            $this->punch('out', '2026-09-20 00:10:00', 2),
        ]), $shift);

        $this->assertSame(1, $paired['session_count']);
        $this->assertSame(20 * 60, $paired['worked_seconds']); // 20 minutes worked
        $this->assertSame('2026-09-20 00:10:00', $paired['last_clock_out']->format('Y-m-d H:i:s'));
    }

    public function test_out_of_order_arrival_produces_the_same_result_as_sorted_input(): void
    {
        $sortedPunches = [
            $this->punch('in', '2026-09-19 09:00:00', 1),
            $this->punch('out', '2026-09-19 13:00:00', 2),
            $this->punch('in', '2026-09-19 14:00:00', 3),
            $this->punch('out', '2026-09-19 18:00:00', 4),
        ];

        $sortedResult = $this->sessions->pairSessions(new Collection($sortedPunches));

        // Same punches, deliberately fed out of chronological order (as if a
        // delayed offline sync inserted an earlier punch after later ones).
        $shuffled = [$sortedPunches[2], $sortedPunches[0], $sortedPunches[3], $sortedPunches[1]];
        $shuffledResult = $this->sessions->pairSessions(new Collection($shuffled));

        $this->assertSame($sortedResult['worked_seconds'], $shuffledResult['worked_seconds']);
        $this->assertSame($sortedResult['session_count'], $shuffledResult['session_count']);
        $this->assertSame(
            $sortedResult['first_clock_in']->format('Y-m-d H:i:s'),
            $shuffledResult['first_clock_in']->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $sortedResult['last_clock_out']->format('Y-m-d H:i:s'),
            $shuffledResult['last_clock_out']->format('Y-m-d H:i:s')
        );
    }

    public function test_summarize_late_from_first_in_and_early_departure_from_last_out(): void
    {
        $shift = (object) ['start_time' => '09:00', 'end_time' => '18:00', 'grace_minutes' => 5];

        $paired = $this->sessions->pairSessions(new Collection([
            $this->punch('in', '2026-09-19 09:20:00', 1), // 20 min late
            $this->punch('out', '2026-09-19 16:00:00', 2), // left 2h early
        ]));

        $summary = $this->sessions->summarize(
            $paired,
            $shift,
            Carbon::parse('2026-09-19 09:00:00'),
            Carbon::parse('2026-09-19 18:00:00'),
        );

        $this->assertSame(20, $summary['late_minutes']);
        $this->assertSame(120, $summary['early_departure_minutes']);
        $this->assertSame(0, $summary['overtime_minutes']);
        $this->assertSame('early_departure', $summary['attendance_status']);
    }

    public function test_single_pair_summarize_matches_todays_single_session_status_rules(): void
    {
        $shift = (object) ['start_time' => '09:00', 'end_time' => '18:00', 'grace_minutes' => 5];

        // On time in, on time out (within grace, not a second past shift end)
        // -> present, byte-identical to today's mobile clock-in/out behaviour
        // for allow_multiple_punches = 0 tenants.
        $paired = $this->sessions->pairSessions(new Collection([
            $this->punch('in', '2026-09-19 09:00:00', 1),
            $this->punch('out', '2026-09-19 17:55:00', 2),
        ]));

        $summary = $this->sessions->summarize(
            $paired,
            $shift,
            Carbon::parse('2026-09-19 09:00:00'),
            Carbon::parse('2026-09-19 18:00:00'),
        );

        $this->assertSame(0, $summary['late_minutes']);
        $this->assertSame(0, $summary['early_departure_minutes']);
        $this->assertSame('present', $summary['attendance_status']);
    }
}
