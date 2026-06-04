<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Support;

use Teran\Sri\Signing\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(private readonly \DateTimeImmutable $instant)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}
