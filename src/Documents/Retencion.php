<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Exceptions\ValidationException;

final class Retencion
{
    /**
     * @param DocSustento[] $docsSustento
     */
    public function __construct(
        public readonly InfoTributaria $infoTributaria,
        public readonly string  $fechaEmision,
        public readonly string  $dirEstablecimiento,
        public readonly string  $tipoIdentificacionSujetoRetenido,
        public readonly string  $razonSocialSujetoRetenido,
        public readonly string  $identificacionSujetoRetenido,
        public readonly string  $periodoFiscal,
        public readonly array   $docsSustento,
        public readonly ?string $contribuyenteEspecial = null,
        public readonly ?string $obligadoContabilidad = null,
        public readonly ?string $tipoSujetoRetenido = null,
        public readonly ?string $parteRel = null,
    ) {
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaEmision)) {
            throw new ValidationException("Retencion: fechaEmision inválida '$fechaEmision' (formato dd/MM/yyyy).");
        }
        if ($docsSustento === []) {
            throw new ValidationException('Retencion: debe tener al menos un docSustento.');
        }
        foreach ($docsSustento as $d) {
            if (!$d instanceof DocSustento) {
                throw new ValidationException('Retencion: cada docSustento debe ser instancia de DocSustento.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $infoTributariaRaw */
        $infoTributariaRaw = is_array($data['infoTributaria'] ?? null) ? $data['infoTributaria'] : [];
        $info = InfoTributaria::fromArray($infoTributariaRaw);
        /** @var array<string, mixed> $c */
        $c = is_array($data['infoCompRetencion'] ?? null) ? $data['infoCompRetencion'] : [];

        /** @var array<int, array<string, mixed>> $rawDocs */
        $rawDocs = is_array($data['docsSustento'] ?? null) ? $data['docsSustento'] : [];
        $docsSustento = array_map(
            static fn (array $doc): DocSustento => DocSustento::fromArray($doc),
            $rawDocs,
        );

        return new self(
            infoTributaria: $info,
            fechaEmision: self::coerceStr($c['fechaEmision'] ?? null),
            dirEstablecimiento: self::coerceStr($c['dirEstablecimiento'] ?? null),
            tipoIdentificacionSujetoRetenido: self::coerceStr($c['tipoIdentificacionSujetoRetenido'] ?? null),
            razonSocialSujetoRetenido: self::coerceStr($c['razonSocialSujetoRetenido'] ?? null),
            identificacionSujetoRetenido: self::coerceStr($c['identificacionSujetoRetenido'] ?? null),
            periodoFiscal: self::coerceStr($c['periodoFiscal'] ?? null),
            docsSustento: $docsSustento,
            contribuyenteEspecial: isset($c['contribuyenteEspecial']) ? self::coerceStr($c['contribuyenteEspecial']) : null,
            obligadoContabilidad: isset($c['obligadoContabilidad']) ? self::coerceStr($c['obligadoContabilidad']) : null,
            tipoSujetoRetenido: isset($c['tipoSujetoRetenido']) ? self::coerceStr($c['tipoSujetoRetenido']) : null,
            parteRel: isset($c['parteRel']) ? self::coerceStr($c['parteRel']) : null,
        );
    }

    private static function coerceStr(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
