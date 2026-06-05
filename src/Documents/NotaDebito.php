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
        public readonly ?string $dirEstablecimiento = null,
        public readonly ?string $contribuyenteEspecial = null,
        public readonly ?string $rise = null,
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

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $infoTributariaRaw */
        $infoTributariaRaw = is_array($data['infoTributaria'] ?? null) ? $data['infoTributaria'] : [];
        $info = InfoTributaria::fromArray($infoTributariaRaw);
        /** @var array<string, mixed> $f */
        $f = is_array($data['infoNotaDebito'] ?? null) ? $data['infoNotaDebito'] : [];

        /** @var array<int, array<string, mixed>> $rawImpuestos */
        $rawImpuestos = is_array($f['impuestos'] ?? null) ? $f['impuestos'] : [];
        $impuestos = array_map(
            static fn (array $imp): Impuesto => Impuesto::fromArray($imp),
            $rawImpuestos,
        );
        /** @var array<int, array<string, mixed>> $rawPagos */
        $rawPagos = is_array($f['pagos'] ?? null) ? $f['pagos'] : [];
        $pagos = array_map(
            static fn (array $pago): Pago => Pago::fromArray($pago),
            $rawPagos,
        );
        /** @var array<int, array<string, mixed>> $rawMotivos */
        $rawMotivos = is_array($data['motivos'] ?? null) ? $data['motivos'] : [];
        $motivos = array_map(
            static fn (array $m): Motivo => Motivo::fromArray($m),
            $rawMotivos,
        );

        return new self(
            infoTributaria: $info,
            fechaEmision: self::coerceStr($f['fechaEmision'] ?? null),
            tipoIdentificacionComprador: self::coerceStr($f['tipoIdentificacionComprador'] ?? null),
            razonSocialComprador: self::coerceStr($f['razonSocialComprador'] ?? null),
            identificacionComprador: self::coerceStr($f['identificacionComprador'] ?? null),
            codDocModificado: self::coerceStr($f['codDocModificado'] ?? null),
            numDocModificado: self::coerceStr($f['numDocModificado'] ?? null),
            fechaEmisionDocSustento: self::coerceStr($f['fechaEmisionDocSustento'] ?? null),
            totalSinImpuestos: Money::of(self::coerceStr($f['totalSinImpuestos'] ?? '0')),
            valorTotal: Money::of(self::coerceStr($f['valorTotal'] ?? '0')),
            impuestos: $impuestos,
            pagos: $pagos,
            motivos: $motivos,
            obligadoContabilidad: self::coerceStr($f['obligadoContabilidad'] ?? 'NO'),
            dirEstablecimiento: isset($f['dirEstablecimiento']) ? self::coerceStr($f['dirEstablecimiento']) : null,
            contribuyenteEspecial: isset($f['contribuyenteEspecial']) ? self::coerceStr($f['contribuyenteEspecial']) : null,
            rise: isset($f['rise']) ? self::coerceStr($f['rise']) : null,
        );
    }

    private static function coerceStr(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
