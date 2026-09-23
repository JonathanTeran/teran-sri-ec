<?php

declare(strict_types=1);

namespace Teran\Sri\Dto;

class Mensaje
{
    public function __construct(
        public readonly string $identificador,
        public readonly string $mensaje,
        public readonly ?string $informacionAdicional,
        public readonly string $tipo
    ) {}

    public static function fromSoap(object $data): self
    {
        return new self(
            (string)($data->identificador ?? ''),
            (string)($data->mensaje ?? ''),
            (string)($data->informacionAdicional ?? null),
            (string)($data->tipo ?? 'ERROR')
        );
    }

    /**
     * El mensaje con su información adicional, que es donde el SRI dice el
     * motivo concreto («ARCHIVO NO CUMPLE ESTRUCTURA XML: No existe un
     * contribuyente registrado con el RUC …»).
     */
    public function texto(): string
    {
        $detalle = trim((string) $this->informacionAdicional);

        if ($detalle === '' || str_contains($this->mensaje, $detalle)) {
            return $this->mensaje;
        }

        return $this->mensaje . ': ' . $detalle;
    }
}
