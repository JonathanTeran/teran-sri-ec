<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Signing;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Signing\SystemClock;
use Teran\Sri\Tests\Support\FixedClock;

class ClockTest extends TestCase
{
    public function test_system_clock_returns_now(): void
    {
        $before = new \DateTimeImmutable();
        $now = (new SystemClock())->now();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    public function test_fixed_clock_returns_fixed_instant(): void
    {
        $instant = new \DateTimeImmutable('2026-01-26T10:00:00-05:00');
        $this->assertEquals($instant, (new FixedClock($instant))->now());
    }
}
