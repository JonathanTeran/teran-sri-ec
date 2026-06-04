<?php

declare(strict_types=1);

namespace Teran\Sri\Emission;

final class Message
{
    public function __construct(
        public readonly string $identificador,
        public readonly string $mensaje,
        public readonly string $tipo = '',
        public readonly string $informacionAdicional = '',
    ) {
    }
}
