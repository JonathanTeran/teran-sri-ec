<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Exceptions\ValidationException;

final class GuiaRemision
{
    /**
     * @param Destinatario[] $destinatarios
     */
    public function __construct(
        public readonly InfoTributaria $infoTributaria,
        public readonly string  $dirEstablecimiento,
        public readonly string  $dirPartida,
        public readonly string  $razonSocialTransportista,
        public readonly string  $tipoIdentificacionTransportista,
        public readonly string  $rucTransportista,
        public readonly string  $fechaIniTransporte,
        public readonly string  $fechaFinTransporte,
        public readonly string  $placa,
        public readonly array   $destinatarios,
        public readonly ?string $rise = null,
        public readonly ?string $obligadoContabilidad = null,
        public readonly ?string $contribuyenteEspecial = null,
    ) {
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaIniTransporte)) {
            throw new ValidationException("GuiaRemision: fechaIniTransporte inválida '$fechaIniTransporte' (formato dd/MM/yyyy).");
        }
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $fechaFinTransporte)) {
            throw new ValidationException("GuiaRemision: fechaFinTransporte inválida '$fechaFinTransporte' (formato dd/MM/yyyy).");
        }
        if ($destinatarios === []) {
            throw new ValidationException('GuiaRemision: debe tener al menos un destinatario.');
        }
        foreach ($destinatarios as $d) {
            if (!$d instanceof Destinatario) {
                throw new ValidationException('GuiaRemision: cada destinatario debe ser instancia de Destinatario.');
            }
        }
    }

    public static function fromArray(array $data): self
    {
        $info = InfoTributaria::fromArray($data['infoTributaria'] ?? []);
        $g = $data['infoGuiaRemision'] ?? [];

        $destinatarios = array_map(
            static fn (array $dest): Destinatario => Destinatario::fromArray($dest),
            $data['destinatarios'] ?? [],
        );

        return new self(
            infoTributaria: $info,
            dirEstablecimiento: (string) ($g['dirEstablecimiento'] ?? ''),
            dirPartida: (string) ($g['dirPartida'] ?? ''),
            razonSocialTransportista: (string) ($g['razonSocialTransportista'] ?? ''),
            tipoIdentificacionTransportista: (string) ($g['tipoIdentificacionTransportista'] ?? ''),
            rucTransportista: (string) ($g['rucTransportista'] ?? ''),
            fechaIniTransporte: (string) ($g['fechaIniTransporte'] ?? ''),
            fechaFinTransporte: (string) ($g['fechaFinTransporte'] ?? ''),
            placa: (string) ($g['placa'] ?? ''),
            destinatarios: $destinatarios,
            rise: isset($g['rise']) ? (string) $g['rise'] : null,
            obligadoContabilidad: isset($g['obligadoContabilidad']) ? (string) $g['obligadoContabilidad'] : null,
            contribuyenteEspecial: isset($g['contribuyenteEspecial']) ? (string) $g['contribuyenteEspecial'] : null,
        );
    }
}
