<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Catalogs2\FormaPago;
use Teran\Sri\Exceptions\ValidationException;

final class Pago
{
    public function __construct(
        public readonly FormaPago $formaPago,
        public readonly Money $total,
        public readonly ?int $plazo = null,
        public readonly ?string $unidadTiempo = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $codigo = (string) ($data['formaPago'] ?? '');
        $formaPago = FormaPago::tryFrom($codigo);
        if ($formaPago === null) {
            throw new ValidationException("Pago: forma de pago desconocida '$codigo'.");
        }

        return new self(
            formaPago: $formaPago,
            total: Money::of($data['total'] ?? 0),
            plazo: isset($data['plazo']) ? (int) $data['plazo'] : null,
            unidadTiempo: isset($data['unidadTiempo']) ? (string) $data['unidadTiempo'] : null,
        );
    }
}
