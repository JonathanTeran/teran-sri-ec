<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class Factura
{
    /**
     * @param Detalle[] $detalles
     * @param Impuesto[] $totalConImpuestos
     * @param Pago[] $pagos
     */
    public function __construct(
        public readonly InfoTributaria $infoTributaria,
        public readonly string $fechaEmision,
        public readonly string $tipoIdentificacionComprador,
        public readonly string $razonSocialComprador,
        public readonly string $identificacionComprador,
        public readonly Money $totalSinImpuestos,
        public readonly Money $totalDescuento,
        public readonly Money $importeTotal,
        public readonly array $totalConImpuestos,
        public readonly array $detalles,
        public readonly array $pagos,
        public readonly string $obligadoContabilidad = 'NO',
    ) {
        if ($detalles === []) {
            throw new ValidationException('Factura: debe tener al menos un detalle.');
        }
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaEmision)) {
            throw new ValidationException("Factura: fechaEmision inválida '$fechaEmision' (formato dd/MM/yyyy).");
        }
        foreach ($detalles as $d) {
            if (!$d instanceof Detalle) {
                throw new ValidationException('Factura: cada detalle debe ser instancia de Detalle.');
            }
        }
    }

    public static function fromArray(array $data): self
    {
        $info = InfoTributaria::fromArray($data['infoTributaria'] ?? []);
        $f = $data['infoFactura'] ?? [];

        $totalConImpuestos = array_map(
            static fn (array $imp): Impuesto => Impuesto::fromArray($imp),
            $f['totalConImpuestos'] ?? [],
        );
        $detalles = array_map(
            static fn (array $det): Detalle => Detalle::fromArray($det),
            $data['detalles'] ?? [],
        );
        $pagos = array_map(
            static fn (array $pago): Pago => Pago::fromArray($pago),
            $f['pagos'] ?? [],
        );

        return new self(
            infoTributaria: $info,
            fechaEmision: (string) ($f['fechaEmision'] ?? ''),
            tipoIdentificacionComprador: (string) ($f['tipoIdentificacionComprador'] ?? ''),
            razonSocialComprador: (string) ($f['razonSocialComprador'] ?? ''),
            identificacionComprador: (string) ($f['identificacionComprador'] ?? ''),
            totalSinImpuestos: Money::of($f['totalSinImpuestos'] ?? 0),
            totalDescuento: Money::of($f['totalDescuento'] ?? 0),
            importeTotal: Money::of($f['importeTotal'] ?? $f['importetotal'] ?? 0),
            totalConImpuestos: $totalConImpuestos,
            detalles: $detalles,
            pagos: $pagos,
            obligadoContabilidad: (string) ($f['obligadoContabilidad'] ?? 'NO'),
        );
    }
}
