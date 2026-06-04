<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;

final class AuthorizationOutcome
{
    /** @param Message[] $mensajes */
    public function __construct(
        public readonly string $estado,
        public readonly ?string $numeroAutorizacion = null,
        public readonly ?string $fechaAutorizacion = null,
        public readonly ?string $comprobante = null,
        public readonly array $mensajes = [],
    ) {
    }
}
