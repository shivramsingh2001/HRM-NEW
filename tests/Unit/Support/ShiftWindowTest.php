<?php

namespace Tests\Unit\Support;

use App\Support\ShiftWindow;
use PHPUnit\Framework\TestCase;

class ShiftWindowTest extends TestCase
{
    public function test_day_shift_is_not_overnight_and_ends_same_day(): void
    {
        $shift = ['start_time' => '09:30:00', 'end_time' => '18:30:00', 'is_overnight' => 0];

        $this->assertFalse(ShiftWindow::isOvernight($shift));
        [$start, $end] = ShiftWindow::window('2026-10-01', $shift);
        $this->assertSame('2026-10-01 09:30', $start->format('Y-m-d H:i'));
        $this->assertSame('2026-10-01 18:30', $end->format('Y-m-d H:i'));
        $this->assertSame(540, ShiftWindow::spanMinutes('09:30', '18:30'));
    }

    public function test_night_shift_ends_next_day(): void
    {
        $shift = (object) ['start_time' => '22:00', 'end_time' => '06:00', 'is_overnight' => true];

        $this->assertTrue(ShiftWindow::isOvernight($shift));
        [$start, $end] = ShiftWindow::window('2026-10-01', $shift);
        $this->assertSame('2026-10-01 22:00', $start->format('Y-m-d H:i'));
        $this->assertSame('2026-10-02 06:00', $end->format('Y-m-d H:i'));
        $this->assertSame(480, ShiftWindow::spanMinutes('22:00', '06:00', true));
    }

    public function test_without_flag_the_times_decide(): void
    {
        $this->assertTrue(ShiftWindow::isOvernight(['start_time' => '20:00', 'end_time' => '08:00']));
        $this->assertFalse(ShiftWindow::isOvernight(['start_time' => '08:00', 'end_time' => '20:00']));
        $this->assertFalse(ShiftWindow::isOvernight(null));
        $this->assertSame(720, ShiftWindow::spanMinutes('20:00', '08:00'));
    }

    public function test_flag_wins_over_times(): void
    {
        $this->assertFalse(ShiftWindow::isOvernight(['start_time' => '20:00', 'end_time' => '08:00', 'is_overnight' => 0]));
    }

    public function test_working_hours_deduct_break(): void
    {
        $this->assertSame(8.0, ShiftWindow::workingHours('09:30', '18:30', 60));
        $this->assertSame(7.5, ShiftWindow::workingHours('22:00', '06:00', 30, true));
        $this->assertSame(0.0, ShiftWindow::workingHours(null, '06:00', 0));
    }

    public function test_same_start_and_end_is_a_24_hour_shift(): void
    {
        $this->assertTrue(ShiftWindow::isOvernight(['start_time' => '07:00', 'end_time' => '07:00']));
        $this->assertSame(1440, ShiftWindow::spanMinutes('07:00', '07:00'));
    }

    public function test_times_must_match_the_checkbox(): void
    {
        $this->assertNull(ShiftWindow::timesError('09:00', '18:00', false));
        $this->assertNull(ShiftWindow::timesError('22:00', '06:00', true));
        $this->assertNotNull(ShiftWindow::timesError('22:00', '06:00', false));
        $this->assertNotNull(ShiftWindow::timesError('09:00', '18:00', true));
        $this->assertNotNull(ShiftWindow::timesError('09:00', '09:00', false));
    }
}
