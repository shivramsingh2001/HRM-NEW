<?php

namespace Tests\Unit\Expense;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_to_cents_handles_strings_floats_ints_and_blanks(): void
    {
        $this->assertSame(123450, Money::toCents('1234.50'));
        $this->assertSame(123450, Money::toCents(1234.5));
        $this->assertSame(123400, Money::toCents(1234));
        $this->assertSame(0, Money::toCents(null));
        $this->assertSame(0, Money::toCents(''));
        $this->assertSame(1, Money::toCents('0.01'));
    }

    public function test_float_representation_errors_do_not_leak_into_cents(): void
    {
        // 0.1 + 0.2 !== 0.3 in floats; in paise it is exact.
        $this->assertNotSame(0.3, 0.1 + 0.2);
        $this->assertSame(Money::toCents(0.3), Money::toCents(0.1) + Money::toCents(0.2));

        // Sub-paise input is ROUNDED, never truncated (19.999 -> ₹20.00). Half-paise values such as
        // 1.005 are deliberately NOT asserted: 1.005 * 100 is 100.49999999999999 in binary floating
        // point and PHP's round() handling of that differs between PHP versions. That ambiguity is
        // exactly why amounts with more than 2 decimals are rejected by StoreExpenseRequest before
        // they reach Money — the helper is not a substitute for validation.
        $this->assertSame(2000, Money::toCents('19.999'));
    }

    public function test_round_trip_and_format(): void
    {
        $this->assertSame(1234.5, Money::fromCents(123450));
        $this->assertSame('1,234.50', Money::format(123450));
        $this->assertSame('0.00', Money::format(0));
        $this->assertSame('-5.00', Money::format(-500));
        $this->assertSame('100,000.00', Money::format(10000000), 'standard thousands grouping (not lakh grouping)');
    }

    public function test_sub_paise_detection(): void
    {
        $this->assertTrue(Money::hasSubPaise('10.999'));
        $this->assertTrue(Money::hasSubPaise(0.001));
        $this->assertFalse(Money::hasSubPaise('10.99'));
        $this->assertFalse(Money::hasSubPaise(100));
        $this->assertFalse(Money::hasSubPaise('0.10'));
    }

    public function test_summing_many_small_amounts_is_exact(): void
    {
        $cents = 0;
        for ($i = 0; $i < 1000; $i++) {
            $cents += Money::toCents('0.10');
        }
        $this->assertSame(10000, $cents);

        $float = 0.0;
        for ($i = 0; $i < 1000; $i++) {
            $float += 0.10;
        }
        $this->assertNotSame(100.0, $float, 'floats drift where paise do not');
    }
}
