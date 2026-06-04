<?php

declare(strict_types=1);

namespace Teran\Sri\Batch;

/**
 * Limita la tasa de llamadas al SRI. `throttle()` bloquea lo necesario antes
 * de cada llamada (implementaciones reales: token-bucket global / por RUC).
 */
interface RateLimiterInterface
{
    public function throttle(string $key = 'sri'): void;
}
