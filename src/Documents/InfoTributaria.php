<?php

declare(strict_types=1);

namespace Teran\Sri\Documents;

use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Catalogs2\TipoEmision;
use Teran\Sri\Exceptions\ValidationException;

final class InfoTributaria
{
    public function __construct(
        public readonly Ambiente $ambiente,
        public readonly string $razonSocial,
        public readonly string $ruc,
        public readonly string $estab,
        public readonly string $ptoEmi,
        public readonly string $secuencial,
        public readonly string $dirMatriz,
        public readonly TipoEmision $tipoEmision = TipoEmision::Normal,
        public readonly ?string $nombreComercial = null,
    ) {
        if (strlen($ruc) !== 13 || !ctype_digit($ruc)) {
            throw new ValidationException("InfoTributaria: RUC inválido '$ruc' (deben ser 13 dígitos).");
        }
        if ($razonSocial === '') {
            throw new ValidationException('InfoTributaria: "razonSocial" es obligatoria.');
        }
        if ($dirMatriz === '') {
            throw new ValidationException('InfoTributaria: "dirMatriz" es obligatoria.');
        }
        foreach (['estab' => $estab, 'ptoEmi' => $ptoEmi] as $campo => $valor) {
            if (strlen($valor) !== 3 || !ctype_digit($valor)) {
                throw new ValidationException("InfoTributaria: \"$campo\" debe tener 3 dígitos.");
            }
        }
        if (strlen($secuencial) !== 9 || !ctype_digit($secuencial)) {
            throw new ValidationException('InfoTributaria: "secuencial" debe tener 9 dígitos.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $ambienteCodigo = self::coerceStr($data['ambiente'] ?? null);
        $ambiente = Ambiente::tryFrom($ambienteCodigo);
        if ($ambiente === null) {
            throw new ValidationException("InfoTributaria: ambiente inválido '$ambienteCodigo' (use '1' o '2').");
        }

        $tipoEmisionCodigo = self::coerceStr($data['tipoEmision'] ?? '1');
        $tipoEmision = TipoEmision::tryFrom($tipoEmisionCodigo);
        if ($tipoEmision === null) {
            throw new ValidationException("InfoTributaria: tipoEmision inválido '$tipoEmisionCodigo'.");
        }

        return new self(
            ambiente: $ambiente,
            razonSocial: self::coerceStr($data['razonSocial'] ?? null),
            ruc: self::coerceStr($data['ruc'] ?? null),
            estab: self::coerceStr($data['estab'] ?? null),
            ptoEmi: self::coerceStr($data['ptoEmi'] ?? null),
            secuencial: self::coerceStr($data['secuencial'] ?? null),
            dirMatriz: self::coerceStr($data['dirMatriz'] ?? null),
            tipoEmision: $tipoEmision,
            nombreComercial: isset($data['nombreComercial']) ? self::coerceStr($data['nombreComercial']) : null,
        );
    }

    /** Safely coerce a mixed value to string. Returns '' for null, array, or non-scalar. */
    private static function coerceStr(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
