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

    public static function fromArray(array $data): self
    {
        $info = InfoTributaria::fromArray($data['infoTributaria'] ?? []);
        $c = $data['infoCompRetencion'] ?? [];

        $docsSustento = array_map(
            static fn (array $doc): DocSustento => DocSustento::fromArray($doc),
            $data['docsSustento'] ?? [],
        );

        return new self(
            infoTributaria: $info,
            fechaEmision: (string) ($c['fechaEmision'] ?? ''),
            dirEstablecimiento: (string) ($c['dirEstablecimiento'] ?? ''),
            tipoIdentificacionSujetoRetenido: (string) ($c['tipoIdentificacionSujetoRetenido'] ?? ''),
            razonSocialSujetoRetenido: (string) ($c['razonSocialSujetoRetenido'] ?? ''),
            identificacionSujetoRetenido: (string) ($c['identificacionSujetoRetenido'] ?? ''),
            periodoFiscal: (string) ($c['periodoFiscal'] ?? ''),
            docsSustento: $docsSustento,
            contribuyenteEspecial: isset($c['contribuyenteEspecial']) ? (string) $c['contribuyenteEspecial'] : null,
            obligadoContabilidad: isset($c['obligadoContabilidad']) ? (string) $c['obligadoContabilidad'] : null,
            tipoSujetoRetenido: isset($c['tipoSujetoRetenido']) ? (string) $c['tipoSujetoRetenido'] : null,
            parteRel: isset($c['parteRel']) ? (string) $c['parteRel'] : null,
        );
    }
}
