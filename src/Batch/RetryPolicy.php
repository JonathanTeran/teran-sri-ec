<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

/**
 * Política de reintentos para fallos transitorios y estado EN_PROCESO.
 * Backoff exponencial: delay = baseDelaySeconds * 2^(attempt-1), con tope.
 */
final class RetryPolicy
{
    public function __construct(
        public readonly int $maxAttempts = 5,
        public readonly int $baseDelaySeconds = 3,
        public readonly int $maxDelaySeconds = 600,
    ) {
    }

    public function shouldRetry(int $attempts): bool
    {
        return $attempts < $this->maxAttempts;
    }

    public function delaySeconds(int $attempt): int
    {
        $delay = $this->baseDelaySeconds * (2 ** ($attempt - 1));
        return (int) min($delay, $this->maxDelaySeconds);
    }
}
