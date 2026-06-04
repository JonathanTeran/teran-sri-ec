<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Teran\Sri\Signing\ClockInterface;

/**
 * Test clock that returns a fixed instant but counts every call to now().
 * Used to assert that the signer consults the clock exactly once per sign().
 */
final class CountingClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(private readonly \DateTimeImmutable $instant)
    {
    }

    public function now(): \DateTimeImmutable
    {
        $this->calls++;
        return $this->instant;
    }
}
