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

    public static function fromArray(array $data): self
    {
        return new self(
            identificacionDestinatario: (string) ($data['identificacionDestinatario'] ?? ''),
            razonSocialDestinatario: (string) ($data['razonSocialDestinatario'] ?? ''),
            dirDestinatario: (string) ($data['dirDestinatario'] ?? ''),
            motivoTraslado: (string) ($data['motivoTraslado'] ?? ''),
            detalles: $data['detalles'] ?? [],
            docAduaneroUnico: isset($data['docAduaneroUnico']) ? (string) $data['docAduaneroUnico'] : null,
            codEstabDestino: isset($data['codEstabDestino']) ? (string) $data['codEstabDestino'] : null,
            ruta: isset($data['ruta']) ? (string) $data['ruta'] : null,
            codDocSustento: isset($data['codDocSustento']) ? (string) $data['codDocSustento'] : null,
            numDocSustento: isset($data['numDocSustento']) ? (string) $data['numDocSustento'] : null,
            numAutDocSustento: isset($data['numAutDocSustento']) ? (string) $data['numAutDocSustento'] : null,
            fechaEmisionDocSustento: isset($data['fechaEmisionDocSustento']) ? (string) $data['fechaEmisionDocSustento'] : null,
        );
    }
}
