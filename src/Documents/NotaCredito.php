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
        public readonly ?string $dirEstablecimiento = null,
        public readonly ?string $contribuyenteEspecial = null,
        public readonly ?string $rise = null,
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

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $infoTributariaRaw */
        $infoTributariaRaw = is_array($data['infoTributaria'] ?? null) ? $data['infoTributaria'] : [];
        $info = InfoTributaria::fromArray($infoTributariaRaw);
        /** @var array<string, mixed> $f */
        $f = is_array($data['infoNotaCredito'] ?? null) ? $data['infoNotaCredito'] : [];

        /** @var array<int, array<string, mixed>> $rawTci */
        $rawTci = is_array($f['totalConImpuestos'] ?? null) ? $f['totalConImpuestos'] : [];
        $totalConImpuestos = array_map(
            static fn (array $imp): Impuesto => Impuesto::fromArray($imp),
            $rawTci,
        );
        /** @var array<int, array<string, mixed>> $rawDetalles */
        $rawDetalles = is_array($data['detalles'] ?? null) ? $data['detalles'] : [];
        $detalles = array_map(
            static fn (array $det): Detalle => Detalle::fromArray($det),
            $rawDetalles,
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
            valorModificacion: Money::of(self::coerceStr($f['valorModificacion'] ?? '0')),
            totalConImpuestos: $totalConImpuestos,
            detalles: $detalles,
            motivo: self::coerceStr($f['motivo'] ?? null),
            obligadoContabilidad: self::coerceStr($f['obligadoContabilidad'] ?? 'NO'),
            moneda: self::coerceStr($f['moneda'] ?? 'DOLAR'),
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
