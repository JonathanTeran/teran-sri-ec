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

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $infoTributariaRaw */
        $infoTributariaRaw = is_array($data['infoTributaria'] ?? null) ? $data['infoTributaria'] : [];
        $info = InfoTributaria::fromArray($infoTributariaRaw);
        /** @var array<string, mixed> $g */
        $g = is_array($data['infoGuiaRemision'] ?? null) ? $data['infoGuiaRemision'] : [];

        /** @var array<int, array<string, mixed>> $rawDests */
        $rawDests = is_array($data['destinatarios'] ?? null) ? $data['destinatarios'] : [];
        $destinatarios = array_map(
            static fn (array $dest): Destinatario => Destinatario::fromArray($dest),
            $rawDests,
        );

        return new self(
            infoTributaria: $info,
            dirEstablecimiento: self::coerceStr($g['dirEstablecimiento'] ?? null),
            dirPartida: self::coerceStr($g['dirPartida'] ?? null),
            razonSocialTransportista: self::coerceStr($g['razonSocialTransportista'] ?? null),
            tipoIdentificacionTransportista: self::coerceStr($g['tipoIdentificacionTransportista'] ?? null),
            rucTransportista: self::coerceStr($g['rucTransportista'] ?? null),
            fechaIniTransporte: self::coerceStr($g['fechaIniTransporte'] ?? null),
            fechaFinTransporte: self::coerceStr($g['fechaFinTransporte'] ?? null),
            placa: self::coerceStr($g['placa'] ?? null),
            destinatarios: $destinatarios,
            rise: isset($g['rise']) ? self::coerceStr($g['rise']) : null,
            obligadoContabilidad: isset($g['obligadoContabilidad']) ? self::coerceStr($g['obligadoContabilidad']) : null,
            contribuyenteEspecial: isset($g['contribuyenteEspecial']) ? self::coerceStr($g['contribuyenteEspecial']) : null,
        );
    }

    private static function coerceStr(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
