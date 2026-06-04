<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class Impuesto
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $codigoPorcentaje,
        public readonly Money $baseImponible,
        public readonly Money $valor,
        public readonly ?string $tarifa = null,
    ) {
        if ($codigo === '') {
            throw new ValidationException('Impuesto: el campo "codigo" es obligatorio.');
        }
        if ($codigoPorcentaje === '') {
            throw new ValidationException('Impuesto: el campo "codigoPorcentaje" es obligatorio.');
        }
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['codigo'])) {
            throw new ValidationException('Impuesto: falta la llave "codigo".');
        }
        if (!isset($data['codigoPorcentaje'])) {
            throw new ValidationException('Impuesto: falta la llave "codigoPorcentaje".');
        }

        return new self(
            codigo: (string) $data['codigo'],
            codigoPorcentaje: (string) $data['codigoPorcentaje'],
            baseImponible: Money::of($data['baseImponible'] ?? 0),
            valor: Money::of($data['valor'] ?? 0),
            tarifa: isset($data['tarifa']) ? (string) $data['tarifa'] : null,
        );
    }
}
