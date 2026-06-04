<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
