<?php

namespace App\Services\Attendance;

use Carbon\Carbon;

/**
 * Sandwich leave (Company Policies → Working-time thresholds): a run of
 * week-offs / holidays is paid only when the employee worked (or was on paid
 * leave) on the working day just before OR just after it. With the rule off,
 * every week-off / holiday is paid.
 *
 * One implementation for both payroll engines (legacy MonthlyPayrollController
 * and the dynamic engine via PayrollDaysService), so they always pay the same
 * days. A neighbouring day outside the period (previous / next month, or a day
 * not reached yet) counts as worked — an employee is never penalised for a day
 * we cannot see. Whether the rule is on is taken from the policy in force on
 * the first day of each run.
 */
class SandwichRule
{
    /** Day tokens that count as "worked" next to a run of days off. */
    public const WORKED = ['present', 'half_day', 'late', 'overtime', 'early_departure', 'paid_leave', 'first_half_leave', 'second_half_leave'];

    public const OFF = ['holiday', 'weekoff', 'week_off'];

    /**
     * @param  array<string,string>  $days  Y-m-d => day token (present / absent / paid_leave / holiday / weekoff …).
     *                                      A holiday / week-off actually worked must be passed as a worked token.
     * @param  callable(string):bool  $enabledOn  is the sandwich rule on for this date?
     * @return array<string,bool>  every off day => paid?
     */
    public static function offDayPay(array $days, callable $enabledOn): array
    {
        ksort($days);
        $dates = array_keys($days);
        $pay = [];

        $i = 0;
        $n = count($dates);
        while ($i < $n) {
            if (! in_array($days[$dates[$i]], self::OFF, true)) {
                $i++;
                continue;
            }

            // One run of consecutive calendar days off.
            $run = [$dates[$i]];
            $j = $i + 1;
            while ($j < $n && in_array($days[$dates[$j]], self::OFF, true)
                && Carbon::parse($dates[$j - 1])->addDay()->toDateString() === $dates[$j]) {
                $run[] = $dates[$j];
                $j++;
            }

            $paid = ! $enabledOn($run[0])
                || self::workedOrUnknown($days, Carbon::parse($run[0])->subDay()->toDateString())
                || self::workedOrUnknown($days, Carbon::parse(end($run))->addDay()->toDateString());

            foreach ($run as $d) {
                $pay[$d] = $paid;
            }
            $i = $j;
        }

        return $pay;
    }

    private static function workedOrUnknown(array $days, string $date): bool
    {
        return ! array_key_exists($date, $days) || in_array($days[$date], self::WORKED, true);
    }
}
