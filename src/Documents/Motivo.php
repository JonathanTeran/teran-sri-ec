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

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            razon: self::coerceStr($data['razon'] ?? null),
            valor: Money::of(self::coerceStr($data['valor'] ?? '0')),
        );
    }

    private static function coerceStr(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
