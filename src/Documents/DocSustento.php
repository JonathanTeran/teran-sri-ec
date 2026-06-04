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

    public static function fromArray(array $data): self
    {
        return new self(
            codSustento: (string) ($data['codSustento'] ?? ''),
            codDocSustento: (string) ($data['codDocSustento'] ?? ''),
            numDocSustento: (string) ($data['numDocSustento'] ?? ''),
            fechaEmisionDocSustento: (string) ($data['fechaEmisionDocSustento'] ?? ''),
            totalSinImpuestos: (string) ($data['totalSinImpuestos'] ?? ''),
            importeTotal: (string) ($data['importeTotal'] ?? ''),
            impuestosDocSustento: $data['impuestosDocSustento'] ?? [],
            retenciones: $data['retenciones'] ?? [],
            pagos: $data['pagos'] ?? [],
            fechaRegistroContable: isset($data['fechaRegistroContable']) ? (string) $data['fechaRegistroContable'] : null,
            numAutDocSustento: isset($data['numAutDocSustento']) ? (string) $data['numAutDocSustento'] : null,
            pagoLocExt: isset($data['pagoLocExt']) ? (string) $data['pagoLocExt'] : null,
            tipoRegi: isset($data['tipoRegi']) ? (string) $data['tipoRegi'] : null,
            paisEfecPago: isset($data['paisEfecPago']) ? (string) $data['paisEfecPago'] : null,
            aplicConvDobTwordsri: isset($data['aplicConvDobTwordsri']) ? (string) $data['aplicConvDobTwordsri'] : null,
            pagExtSujRetNorLeg: isset($data['pagExtSujRetNorLeg']) ? (string) $data['pagExtSujRetNorLeg'] : null,
            pagoRegFis: isset($data['pagoRegFis']) ? (string) $data['pagoRegFis'] : null,
            totalComprobantesReembolso: isset($data['totalComprobantesReembolso']) ? (string) $data['totalComprobantesReembolso'] : null,
            totalBaseImponibleReembolso: isset($data['totalBaseImponibleReembolso']) ? (string) $data['totalBaseImponibleReembolso'] : null,
            totalImpuestoReembolso: isset($data['totalImpuestoReembolso']) ? (string) $data['totalImpuestoReembolso'] : null,
        );
    }
}
