<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

final class LiquidacionCompra
{
    /**
     * @param Impuesto[] $totalConImpuestos
     * @param Detalle[] $detalles
     * @param Pago[] $pagos
     */
    public function __construct(
        public readonly InfoTributaria $infoTributaria,
        public readonly string $fechaEmision,
        public readonly string $tipoIdentificacionProveedor,
        public readonly string $razonSocialProveedor,
        public readonly string $identificacionProveedor,
        public readonly Money $totalSinImpuestos,
        public readonly Money $totalDescuento,
        public readonly Money $importeTotal,
        public readonly array $totalConImpuestos,
        public readonly array $detalles,
        public readonly array $pagos,
        public readonly string $obligadoContabilidad = 'NO',
        public readonly ?string $dirEstablecimiento = null,
        public readonly ?string $contribuyenteEspecial = null,
        public readonly ?string $direccionProveedor = null,
        public readonly string $moneda = 'DOLAR',
    ) {
        if ($detalles === []) {
            throw new ValidationException('LiquidacionCompra: debe tener al menos un detalle.');
        }
        if ($pagos === []) {
            throw new ValidationException('LiquidacionCompra: debe tener al menos un pago.');
        }
        if (!in_array($obligadoContabilidad, ['SI', 'NO'], true)) {
            throw new ValidationException("LiquidacionCompra: obligadoContabilidad debe ser 'SI' o 'NO'.");
        }
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaEmision)) {
            throw new ValidationException("LiquidacionCompra: fechaEmision inválida '$fechaEmision' (formato dd/MM/yyyy).");
        }
        // El XSD oficial (tipoIdentificacionProveedor) solo admite 04-08.
        if (!preg_match('/^0[4-8]$/', $tipoIdentificacionProveedor)) {
            throw new ValidationException(
                "LiquidacionCompra: tipoIdentificacionProveedor inválido '$tipoIdentificacionProveedor' (use '04' a '08')."
            );
        }
        if ($razonSocialProveedor === '') {
            throw new ValidationException('LiquidacionCompra: "razonSocialProveedor" es obligatoria.');
        }
        if ($identificacionProveedor === '') {
            throw new ValidationException('LiquidacionCompra: "identificacionProveedor" es obligatoria.');
        }
        foreach ($detalles as $d) {
            if (!$d instanceof Detalle) {
                throw new ValidationException('LiquidacionCompra: cada detalle debe ser instancia de Detalle.');
            }
        }
        foreach ($totalConImpuestos as $imp) {
            if (!$imp instanceof Impuesto) {
                throw new ValidationException('LiquidacionCompra: cada totalConImpuesto debe ser instancia de Impuesto.');
            }
        }
        foreach ($pagos as $p) {
            if (!$p instanceof Pago) {
                throw new ValidationException('LiquidacionCompra: cada pago debe ser instancia de Pago.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $infoTributariaRaw */
        $infoTributariaRaw = is_array($data['infoTributaria'] ?? null) ? $data['infoTributaria'] : [];
        $info = InfoTributaria::fromArray($infoTributariaRaw);
        /** @var array<string, mixed> $l */
        $l = is_array($data['infoLiquidacionCompra'] ?? null) ? $data['infoLiquidacionCompra'] : [];

        /** @var array<int, array<string, mixed>> $rawTci */
        $rawTci = is_array($l['totalConImpuestos'] ?? null) ? $l['totalConImpuestos'] : [];
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
        /** @var array<int, array<string, mixed>> $rawPagos */
        $rawPagos = is_array($l['pagos'] ?? null) ? $l['pagos'] : [];
        $pagos = array_map(
            static fn (array $pago): Pago => Pago::fromArray($pago),
            $rawPagos,
        );

        return new self(
            infoTributaria: $info,
            fechaEmision: self::coerceStr($l['fechaEmision'] ?? null),
            tipoIdentificacionProveedor: self::coerceStr($l['tipoIdentificacionProveedor'] ?? null),
            razonSocialProveedor: self::coerceStr($l['razonSocialProveedor'] ?? null),
            identificacionProveedor: self::coerceStr($l['identificacionProveedor'] ?? null),
            totalSinImpuestos: Money::of(self::coerceStr($l['totalSinImpuestos'] ?? '0')),
            totalDescuento: Money::of(self::coerceStr($l['totalDescuento'] ?? '0')),
            importeTotal: Money::of(self::coerceStr($l['importeTotal'] ?? $l['importetotal'] ?? '0')), // alias 1.x (clave en minúscula)
            totalConImpuestos: $totalConImpuestos,
            detalles: $detalles,
            pagos: $pagos,
            obligadoContabilidad: self::coerceStr($l['obligadoContabilidad'] ?? 'NO'),
            dirEstablecimiento: isset($l['dirEstablecimiento']) ? self::coerceStr($l['dirEstablecimiento']) : null,
            contribuyenteEspecial: isset($l['contribuyenteEspecial']) ? self::coerceStr($l['contribuyenteEspecial']) : null,
            direccionProveedor: isset($l['direccionProveedor']) ? self::coerceStr($l['direccionProveedor']) : null,
            moneda: self::coerceStr($l['moneda'] ?? 'DOLAR'),
        );
    }

    private static function coerceStr(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
