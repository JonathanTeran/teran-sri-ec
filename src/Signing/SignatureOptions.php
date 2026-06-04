<?php

declare(strict_types=1);

namespace Teran\Sri\Signing;

use Teran\Sri\Exceptions\SignatureException;

final class SignatureOptions
{
    public function __construct(
        public readonly string $digestAlgorithm = 'sha1',
        public readonly string $description = 'Comprobante electrónico SRI Ecuador',
    ) {
        if (!in_array(strtolower($digestAlgorithm), ['sha1', 'sha256'], true)) {
            throw new SignatureException("Algoritmo de digest no soportado: $digestAlgorithm. Use sha1 o sha256.");
        }
    }
}
