<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Money;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

class MoneyTest extends TestCase
{
    public function test_formats_to_fixed_decimals_without_float_drift(): void
    {
        // 0.1 + 0.2 en float da 0.30000000000000004; con bcmath debe ser exacto.
        $sum = Money::of('0.10')->plus(Money::of('0.20'));
        $this->assertSame('0.30', $sum->format(2));
    }

    public function test_rounds_half_up_to_requested_scale(): void
    {
        $this->assertSame('2.46', Money::of('2.455')->format(2));
        $this->assertSame('1.000000', Money::of('1')->format(6));
    }

    public function test_accepts_int_and_float_inputs(): void
    {
        $this->assertSame('100.00', Money::of(100)->format(2));
        $this->assertSame('12.50', Money::of(12.5)->format(2));
    }

    public function test_rejects_non_numeric(): void
    {
        $this->expectException(ValidationException::class);
        Money::of('abc');
    }
}
