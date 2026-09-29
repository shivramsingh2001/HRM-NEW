<?php

namespace Tests\Unit;

use App\Services\Attendance\AttendancePolicySnapshot;
use PHPUnit\Framework\TestCase;

/**
 * Day Classification on/off switch (Company Policies): off => no hour-based
 * half-day/absent scoring, any work is a present day.
 */
class DayClassificationSwitchTest extends TestCase
{
    private const NINE_HOURS = 9 * 3600;

    public function test_on_scores_short_days_as_before(): void
    {
        $p = AttendancePolicySnapshot::fromRow(['day_classification_enabled' => 1]);

        $this->assertSame('present', $p->classify(9.0, self::NINE_HOURS));
        $this->assertSame('half_day', $p->classify(5.0, self::NINE_HOURS));
        $this->assertSame('absent', $p->classify(2.0, self::NINE_HOURS));
        $this->assertSame('half_day', $p->classify(5.0, 0));
    }

    public function test_off_counts_any_work_as_present(): void
    {
        $p = AttendancePolicySnapshot::fromRow(['day_classification_enabled' => 0]);

        $this->assertSame('present', $p->classify(2.0, self::NINE_HOURS));
        $this->assertSame('present', $p->classify(0.5, 0));
        $this->assertSame('absent', $p->classify(0.0, self::NINE_HOURS));
    }

    public function test_missing_column_defaults_to_on(): void
    {
        $p = AttendancePolicySnapshot::fromRow([]);

        $this->assertTrue($p->dayClassificationEnabled);
        $this->assertTrue($p->toPersistableArray()['day_classification_enabled']);
    }
}
