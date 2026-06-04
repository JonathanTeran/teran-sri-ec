<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
