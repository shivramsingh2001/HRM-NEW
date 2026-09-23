<?php

namespace App\Support;

/**
 * Money as integer paise — the only sanctioned way to compare or add rupee
 * amounts in the Expense module. PHP floats (and DECIMAL columns read back as
 * strings) must never be compared or summed directly: 0.1 + 0.2 !== 0.3, and
 * `abs($a - $b) < 0.01` style tolerances hide real drift.
 */
final class Money
{
    /** "1234.50" | 1234.5 | 1234 | null  ->  123450 */
    public static function toCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    /** 123450 -> 1234.5 (float, for writing back to a DECIMAL(15,2) column) */
    public static function fromCents(int $cents): float
    {
        return $cents / 100;
    }

    /** 123450 -> "1,234.50" */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    /** True when the value has more than two decimal places (e.g. 10.999). */
    public static function hasSubPaise(mixed $amount): bool
    {
        $float = (float) $amount;

        return abs($float * 100 - round($float * 100)) > 1e-6;
    }
}
