<?php

namespace App\Services\Reports;

use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;

/**
 * When a report schedule fires next. All times are app time (config app.timezone,
 * Asia/Kolkata) — the same wall clock users pick the "send at" time in.
 *
 *  daily   — every day, or only the ticked weekdays (`weekdays`, 0 = Sunday)
 *  weekly  — once a week on `weekdays[0]`
 *  monthly — on `day_of_month` (1–28), or the last day of the month when 0
 */
class ReportScheduleTiming
{
    /** First send time strictly after $after (null if the rule can never fire). */
    public static function nextRunAfter(ReportSchedule $s, CarbonImmutable $after): ?CarbonImmutable
    {
        [$h, $m] = array_map('intval', array_pad(explode(':', (string) $s->send_time), 2, 0));
        $day = $after->startOfDay();
        // 400 days covers any monthly rule plus a leap year; a valid rule fires far sooner.
        for ($i = 0; $i < 400; $i++, $day = $day->addDay()) {
            $at = $day->setTime($h, $m);
            if ($at->lessThanOrEqualTo($after) || ! self::matches($s, $day)) {
                continue;
            }

            return $at;
        }

        return null;
    }

    private static function matches(ReportSchedule $s, CarbonImmutable $day): bool
    {
        $weekdays = array_map('intval', (array) ($s->weekdays ?? []));

        return match ($s->frequency) {
            'daily' => $weekdays === [] || in_array($day->dayOfWeek, $weekdays, true),
            'weekly' => in_array($day->dayOfWeek, array_slice($weekdays, 0, 1) ?: [1], true),
            'monthly' => (int) $s->day_of_month === 0
                ? $day->isSameDay($day->endOfMonth())
                : $day->day === (int) $s->day_of_month,
            default => false,
        };
    }

    /** Short human description, e.g. "Daily at 09:00", "Mon, Wed at 18:30", "Monthly on day 1 at 08:00". */
    public static function describe(ReportSchedule $s): string
    {
        $time = substr((string) $s->send_time, 0, 5);
        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $weekdays = array_map('intval', (array) ($s->weekdays ?? []));
        sort($weekdays);

        return match ($s->frequency) {
            'daily' => ($weekdays === [] || count($weekdays) === 7
                ? 'Daily'
                : implode(', ', array_map(fn ($d) => $names[$d] ?? '?', $weekdays))).' at '.$time,
            'weekly' => 'Every '.($names[$weekdays[0] ?? 1] ?? 'Mon').' at '.$time,
            'monthly' => 'Monthly on '.((int) $s->day_of_month === 0 ? 'the last day' : 'day '.(int) $s->day_of_month).' at '.$time,
            default => $time,
        };
    }
}
