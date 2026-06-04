<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

final class NullRateLimiter implements RateLimiterInterface
{
    public function throttle(string $key = 'sri'): void
    {
        // no-op
    }
}
