<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class Detalle
{
    /** @param Impuesto[] $impuestos */
    public function __construct(
        public readonly string $codigoPrincipal,
        public readonly string $descripcion,
        public readonly Money $cantidad,
        public readonly Money $precioUnitario,
        public readonly Money $descuento,
        public readonly Money $precioTotalSinImpuesto,
        public readonly array $impuestos,
        public readonly ?string $codigoAuxiliar = null,
    ) {
        if ($codigoPrincipal === '') {
            throw new ValidationException('Detalle: "codigoPrincipal" es obligatorio.');
        }
        if ($descripcion === '') {
            throw new ValidationException('Detalle: "descripcion" es obligatoria.');
        }
        if ($impuestos === []) {
            throw new ValidationException('Detalle: debe tener al menos un impuesto.');
        }
        foreach ($impuestos as $imp) {
            if (!$imp instanceof Impuesto) {
                throw new ValidationException('Detalle: cada impuesto debe ser una instancia de Impuesto.');
            }
        }
    }

    public static function fromArray(array $data): self
    {
        $impuestos = array_map(
            static fn (array $imp): Impuesto => Impuesto::fromArray($imp),
            $data['impuestos'] ?? [],
        );

        return new self(
            codigoPrincipal: (string) ($data['codigoPrincipal'] ?? ''),
            descripcion: (string) ($data['descripcion'] ?? ''),
            cantidad: Money::of($data['cantidad'] ?? 0),
            precioUnitario: Money::of($data['precioUnitario'] ?? 0),
            descuento: Money::of($data['descuento'] ?? 0),
            precioTotalSinImpuesto: Money::of($data['precioTotalSinImpuesto'] ?? 0),
            impuestos: $impuestos,
            codigoAuxiliar: isset($data['codigoAuxiliar']) ? (string) $data['codigoAuxiliar'] : null,
        );
    }
}
