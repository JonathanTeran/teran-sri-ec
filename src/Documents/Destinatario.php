<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

/**
 * Value object representing a destinatario in a GuíaRemisión.
 * Detalles are held as raw associative arrays (matching the 1.x generator's structure)
 * to guarantee exact parity when serialized.
 */
final class Destinatario
{
    /**
     * @param array<int, array<string, string>> $detalles  Each row: codigoInterno?, codigoAdicional?, descripcion, cantidad, detallesAdicionales?
     */
    public function __construct(
        public readonly string $identificacionDestinatario,
        public readonly string $razonSocialDestinatario,
        public readonly string $dirDestinatario,
        public readonly string $motivoTraslado,
        public readonly array  $detalles,
        public readonly ?string $docAduaneroUnico = null,
        public readonly ?string $codEstabDestino = null,
        public readonly ?string $ruta = null,
        public readonly ?string $codDocSustento = null,
        public readonly ?string $numDocSustento = null,
        public readonly ?string $numAutDocSustento = null,
        public readonly ?string $fechaEmisionDocSustento = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, string>> $detalles */
        $detalles = is_array($data['detalles'] ?? null) ? $data['detalles'] : [];
        return new self(
            identificacionDestinatario: self::coerceStr($data['identificacionDestinatario'] ?? null),
            razonSocialDestinatario: self::coerceStr($data['razonSocialDestinatario'] ?? null),
            dirDestinatario: self::coerceStr($data['dirDestinatario'] ?? null),
            motivoTraslado: self::coerceStr($data['motivoTraslado'] ?? null),
            detalles: $detalles,
            docAduaneroUnico: isset($data['docAduaneroUnico']) ? self::coerceStr($data['docAduaneroUnico']) : null,
            codEstabDestino: isset($data['codEstabDestino']) ? self::coerceStr($data['codEstabDestino']) : null,
            ruta: isset($data['ruta']) ? self::coerceStr($data['ruta']) : null,
            codDocSustento: isset($data['codDocSustento']) ? self::coerceStr($data['codDocSustento']) : null,
            numDocSustento: isset($data['numDocSustento']) ? self::coerceStr($data['numDocSustento']) : null,
            numAutDocSustento: isset($data['numAutDocSustento']) ? self::coerceStr($data['numAutDocSustento']) : null,
            fechaEmisionDocSustento: isset($data['fechaEmisionDocSustento']) ? self::coerceStr($data['fechaEmisionDocSustento']) : null,
        );
    }

    private static function coerceStr(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
