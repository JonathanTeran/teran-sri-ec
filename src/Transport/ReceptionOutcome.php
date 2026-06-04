<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;

final class ReceptionOutcome
{
    /** @param Message[] $mensajes */
    public function __construct(
        public readonly string $estado,
        public readonly array $mensajes = [],
    ) {
    }
}
