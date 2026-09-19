<?php

namespace Tests\Unit\Services\Attendance;

use App\Services\Attendance\AttendanceCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Covers only the two methods added for multi-punch support
 * (earlyDepartureMinutes, resolveAttendanceDate) — the pre-existing methods
 * are exercised indirectly via PunchSessionCalculatorTest.
 */
class AttendanceCalculatorTest extends TestCase
{
    private AttendanceCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new AttendanceCalculator();
    }

    public function test_early_departure_minutes_is_symmetric_to_late_minutes(): void
    {
        $shift = (object) ['grace_minutes' => 10];
        $scheduledEnd = Carbon::parse('2026-09-19 18:00:00');

        $this->assertSame(0, $this->calc->earlyDepartureMinutes($shift, $scheduledEnd, Carbon::parse('2026-09-19 17:55:00')));
        $this->assertSame(30, $this->calc->earlyDepartureMinutes($shift, $scheduledEnd, Carbon::parse('2026-09-19 17:30:00')));
        $this->assertSame(0, $this->calc->earlyDepartureMinutes($shift, $scheduledEnd, Carbon::parse('2026-09-19 18:05:00')));
        $this->assertSame(0, $this->calc->earlyDepartureMinutes(null, $scheduledEnd, Carbon::parse('2026-09-19 17:00:00')));
    }

    public function test_resolve_attendance_date_keeps_today_for_a_day_shift(): void
    {
        $shift = (object) ['start_time' => '09:00', 'end_time' => '18:00'];
        $punchedAt = Carbon::parse('2026-09-19 09:05:00');

        $this->assertSame('2026-09-19', $this->calc->resolveAttendanceDate($punchedAt, $shift));
    }

    public function test_resolve_attendance_date_rolls_back_for_a_post_midnight_night_shift_punch(): void
    {
        $shift = (object) ['start_time' => '22:00', 'end_time' => '06:00'];
        $punchedAt = Carbon::parse('2026-09-20 01:30:00'); // clocking in just after midnight

        $this->assertSame('2026-09-19', $this->calc->resolveAttendanceDate($punchedAt, $shift));
    }

    public function test_resolve_attendance_date_does_not_roll_back_an_early_evening_punch(): void
    {
        $shift = (object) ['start_time' => '20:00', 'end_time' => '04:00'];
        $punchedAt = Carbon::parse('2026-09-19 19:55:00'); // 5 min early for a 20:00 start, not after midnight

        $this->assertSame('2026-09-19', $this->calc->resolveAttendanceDate($punchedAt, $shift));
    }

    public function test_resolve_attendance_date_without_a_shift_uses_the_calendar_day(): void
    {
        $punchedAt = Carbon::parse('2026-09-20 01:30:00');

        $this->assertSame('2026-09-20', $this->calc->resolveAttendanceDate($punchedAt, null));
    }
}
