<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class NotaDebito
{
    /**
     * @param Impuesto[] $impuestos
     * @param Pago[]     $pagos
     * @param Motivo[]   $motivos
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
        public readonly Money $valorTotal,
        public readonly array $impuestos,
        public readonly array $pagos,
        public readonly array $motivos,
        public readonly string $obligadoContabilidad = 'NO',
    ) {
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaEmision)) {
            throw new ValidationException("NotaDebito: fechaEmision inválida '$fechaEmision' (formato dd/MM/yyyy).");
        }
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaEmisionDocSustento)) {
            throw new ValidationException("NotaDebito: fechaEmisionDocSustento inválida '$fechaEmisionDocSustento' (formato dd/MM/yyyy).");
        }
        if ($motivos === []) {
            throw new ValidationException('NotaDebito: debe tener al menos un motivo.');
        }
        foreach ($impuestos as $imp) {
            if (!$imp instanceof Impuesto) {
                throw new ValidationException('NotaDebito: cada impuesto debe ser instancia de Impuesto.');
            }
        }
        foreach ($pagos as $p) {
            if (!$p instanceof Pago) {
                throw new ValidationException('NotaDebito: cada pago debe ser instancia de Pago.');
            }
        }
        foreach ($motivos as $m) {
            if (!$m instanceof Motivo) {
                throw new ValidationException('NotaDebito: cada motivo debe ser instancia de Motivo.');
            }
        }
    }

    public static function fromArray(array $data): self
    {
        $info = InfoTributaria::fromArray($data['infoTributaria'] ?? []);
        $f = $data['infoNotaDebito'] ?? [];

        $impuestos = array_map(
            static fn (array $imp): Impuesto => Impuesto::fromArray($imp),
            $f['impuestos'] ?? [],
        );
        $pagos = array_map(
            static fn (array $pago): Pago => Pago::fromArray($pago),
            $f['pagos'] ?? [],
        );
        $motivos = array_map(
            static fn (array $m): Motivo => Motivo::fromArray($m),
            $data['motivos'] ?? [],
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
            valorTotal: Money::of($f['valorTotal'] ?? 0),
            impuestos: $impuestos,
            pagos: $pagos,
            motivos: $motivos,
            obligadoContabilidad: (string) ($f['obligadoContabilidad'] ?? 'NO'),
        );
    }
}
