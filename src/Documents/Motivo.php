<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class Motivo
{
    public function __construct(
        public readonly string $razon,
        public readonly Money $valor,
    ) {
        if ($razon === '') {
            throw new ValidationException('Motivo: "razon" es obligatoria.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            razon: (string) ($data['razon'] ?? ''),
            valor: Money::of($data['valor'] ?? 0),
        );
    }
}
