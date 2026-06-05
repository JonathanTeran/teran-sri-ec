<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Exceptions\ValidationException;

/**
 * Value object representing a docSustento in a Retención (v2.0.0).
 *
 * All monetary/numeric fields are held as raw strings to guarantee exact
 * parity with the 1.x RetencionGenerator, which casts each value to string
 * and writes it verbatim. Nested rows (impuestosDocSustento, retenciones, pagos)
 * are held as raw associative arrays for the same reason.
 *
 * @property array<int, array<string, string>> $impuestosDocSustento
 * @property array<int, array<string, string>> $retenciones
 * @property array<int, array<string, string>> $pagos
 */
final class DocSustento
{
    /**
     * @param array<int, array<string, string>> $impuestosDocSustento
     * @param array<int, array<string, string>> $retenciones
     * @param array<int, array<string, string>> $pagos
     */
    public function __construct(
        public readonly string  $codSustento,
        public readonly string  $codDocSustento,
        public readonly string  $numDocSustento,
        public readonly string  $fechaEmisionDocSustento,
        public readonly string  $totalSinImpuestos,
        public readonly string  $importeTotal,
        public readonly array   $impuestosDocSustento,
        public readonly array   $retenciones,
        public readonly array   $pagos,
        public readonly ?string $fechaRegistroContable = null,
        public readonly ?string $numAutDocSustento = null,
        public readonly ?string $pagoLocExt = null,
        public readonly ?string $tipoRegi = null,
        public readonly ?string $paisEfecPago = null,
        public readonly ?string $aplicConvDobTwordsri = null,
        public readonly ?string $pagExtSujRetNorLeg = null,
        public readonly ?string $pagoRegFis = null,
        public readonly ?string $totalComprobantesReembolso = null,
        public readonly ?string $totalBaseImponibleReembolso = null,
        public readonly ?string $totalImpuestoReembolso = null,
    ) {
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $this->fechaEmisionDocSustento)) {
            throw new ValidationException("DocSustento: fechaEmisionDocSustento inválida (formato dd/MM/yyyy).");
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, string>> $impuestosDocSustento */
        $impuestosDocSustento = is_array($data['impuestosDocSustento'] ?? null) ? $data['impuestosDocSustento'] : [];
        /** @var array<int, array<string, string>> $retenciones */
        $retenciones = is_array($data['retenciones'] ?? null) ? $data['retenciones'] : [];
        /** @var array<int, array<string, string>> $pagos */
        $pagos = is_array($data['pagos'] ?? null) ? $data['pagos'] : [];
        return new self(
            codSustento: self::coerceStr($data['codSustento'] ?? null),
            codDocSustento: self::coerceStr($data['codDocSustento'] ?? null),
            numDocSustento: self::coerceStr($data['numDocSustento'] ?? null),
            fechaEmisionDocSustento: self::coerceStr($data['fechaEmisionDocSustento'] ?? null),
            totalSinImpuestos: self::coerceStr($data['totalSinImpuestos'] ?? null),
            importeTotal: self::coerceStr($data['importeTotal'] ?? null),
            impuestosDocSustento: $impuestosDocSustento,
            retenciones: $retenciones,
            pagos: $pagos,
            fechaRegistroContable: isset($data['fechaRegistroContable']) ? self::coerceStr($data['fechaRegistroContable']) : null,
            numAutDocSustento: isset($data['numAutDocSustento']) ? self::coerceStr($data['numAutDocSustento']) : null,
            pagoLocExt: isset($data['pagoLocExt']) ? self::coerceStr($data['pagoLocExt']) : null,
            tipoRegi: isset($data['tipoRegi']) ? self::coerceStr($data['tipoRegi']) : null,
            paisEfecPago: isset($data['paisEfecPago']) ? self::coerceStr($data['paisEfecPago']) : null,
            aplicConvDobTwordsri: isset($data['aplicConvDobTwordsri']) ? self::coerceStr($data['aplicConvDobTwordsri']) : null,
            pagExtSujRetNorLeg: isset($data['pagExtSujRetNorLeg']) ? self::coerceStr($data['pagExtSujRetNorLeg']) : null,
            pagoRegFis: isset($data['pagoRegFis']) ? self::coerceStr($data['pagoRegFis']) : null,
            totalComprobantesReembolso: isset($data['totalComprobantesReembolso']) ? self::coerceStr($data['totalComprobantesReembolso']) : null,
            totalBaseImponibleReembolso: isset($data['totalBaseImponibleReembolso']) ? self::coerceStr($data['totalBaseImponibleReembolso']) : null,
            totalImpuestoReembolso: isset($data['totalImpuestoReembolso']) ? self::coerceStr($data['totalImpuestoReembolso']) : null,
        );
    }

    /**
     * Safely coerce a mixed value to string.
     * Returns '' for null, array, object, or resource values.
     */
    private static function coerceStr(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
