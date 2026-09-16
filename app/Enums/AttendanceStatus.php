<?php

namespace App\Enums;

/**
 * The values stored in attendances.attendance_status.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case Late = 'late';
    case EarlyDeparture = 'early_departure';
    case Overtime = 'overtime';
    case OnLeave = 'on_leave';
    case FirstHalfLeave = 'first_half_leave';
    case SecondHalfLeave = 'second_half_leave';
    case Holiday = 'holiday';
    case WeekOff = 'weekoff';

    /**
     * The statuses an admin/HR/manager may set by hand from the team screen.
     */
    public static function markable(): array
    {
        return [
            self::Present->value,
            self::Absent->value,
            self::HalfDay->value,
            self::OnLeave->value,
            self::FirstHalfLeave->value,
            self::SecondHalfLeave->value,
            self::Holiday->value,
            self::WeekOff->value,
        ];
    }

    /**
     * Does this status need clock_in / clock_out to be supplied?
     */
    public function requiresClockTimes(): bool
    {
        return in_array($this, [self::Present, self::HalfDay, self::Late, self::EarlyDeparture, self::Overtime], true);
    }

    public function isLeaveKind(): bool
    {
        return in_array($this, [self::OnLeave, self::FirstHalfLeave, self::SecondHalfLeave], true);
    }

    /**
     * Portion of a working day this status represents (summary math).
     */
    public function dayFraction(): float
    {
        return match ($this) {
            self::Present, self::Late, self::EarlyDeparture, self::Overtime => 1.00,
            self::HalfDay, self::FirstHalfLeave, self::SecondHalfLeave => 0.50,
            // Non-working days — neither present nor absent for summary math.
            self::Absent, self::OnLeave, self::Holiday, self::WeekOff => 0.00,
        };
    }

    /**
     * For a half/full leave marking, the leaves.start_session value to record.
     */
    public function leaveSession(): string
    {
        return match ($this) {
            self::FirstHalfLeave => 'session1',
            self::SecondHalfLeave => 'session2',
            default => 'fullday',
        };
    }
}
