<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class NotaCredito
{
    /**
     * @param Impuesto[] $totalConImpuestos
     * @param Detalle[]  $detalles
     */
    public function __construct(
        public readonly InfoTributaria $infoTributaria,
        public readonly string $fechaEmision,
        public readonly string $tipoIdentificacionComprador,
        public readonly string $razonSocialComprador,
        public readonly string $identificacionComprador,
        public readonly string $codDocModificado,
        public readonly string $numDocModificado,
        public readonly string $fechaEmisionDocSustento,
        public readonly Money $totalSinImpuestos,
        public readonly Money $valorModificacion,
        public readonly array $totalConImpuestos,
        public readonly array $detalles,
        public readonly string $motivo,
        public readonly string $obligadoContabilidad = 'NO',
        public readonly string $moneda = 'DOLAR',
    ) {
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaEmision)) {
            throw new ValidationException("NotaCredito: fechaEmision inválida '$fechaEmision' (formato dd/MM/yyyy).");
        }
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaEmisionDocSustento)) {
            throw new ValidationException("NotaCredito: fechaEmisionDocSustento inválida '$fechaEmisionDocSustento' (formato dd/MM/yyyy).");
        }
        if ($this->motivo === '') {
            throw new ValidationException('NotaCredito: "motivo" es obligatorio.');
        }
        if ($detalles === []) {
            throw new ValidationException('NotaCredito: debe tener al menos un detalle.');
        }
        foreach ($detalles as $d) {
            if (!$d instanceof Detalle) {
                throw new ValidationException('NotaCredito: cada detalle debe ser instancia de Detalle.');
            }
        }
        foreach ($totalConImpuestos as $imp) {
            if (!$imp instanceof Impuesto) {
                throw new ValidationException('NotaCredito: cada totalConImpuesto debe ser instancia de Impuesto.');
            }
        }
    }

    public static function fromArray(array $data): self
    {
        $info = InfoTributaria::fromArray($data['infoTributaria'] ?? []);
        $f = $data['infoNotaCredito'] ?? [];

        $totalConImpuestos = array_map(
            static fn (array $imp): Impuesto => Impuesto::fromArray($imp),
            $f['totalConImpuestos'] ?? [],
        );
        $detalles = array_map(
            static fn (array $det): Detalle => Detalle::fromArray($det),
            $data['detalles'] ?? [],
        );

        return new self(
            infoTributaria: $info,
            fechaEmision: (string) ($f['fechaEmision'] ?? ''),
            tipoIdentificacionComprador: (string) ($f['tipoIdentificacionComprador'] ?? ''),
            razonSocialComprador: (string) ($f['razonSocialComprador'] ?? ''),
            identificacionComprador: (string) ($f['identificacionComprador'] ?? ''),
            codDocModificado: (string) ($f['codDocModificado'] ?? ''),
            numDocModificado: (string) ($f['numDocModificado'] ?? ''),
            fechaEmisionDocSustento: (string) ($f['fechaEmisionDocSustento'] ?? ''),
            totalSinImpuestos: Money::of($f['totalSinImpuestos'] ?? 0),
            valorModificacion: Money::of($f['valorModificacion'] ?? 0),
            totalConImpuestos: $totalConImpuestos,
            detalles: $detalles,
            motivo: (string) ($f['motivo'] ?? ''),
            obligadoContabilidad: (string) ($f['obligadoContabilidad'] ?? 'NO'),
            moneda: (string) ($f['moneda'] ?? 'DOLAR'),
        );
    }
}
