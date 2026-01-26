<?php

declare(strict_types=1);

namespace Teran\Sri\Dto;

class RecepcionResponse
{
    /** @param Mensaje[] $mensajes */
    public function __construct(
        public readonly string $estado,
        public readonly array $mensajes = []
    ) {}

    public static function fromSoap(object $data): self
    {
        $mensajes = [];
        if (isset($data->comprobantes->comprobante->mensajes->mensaje)) {
            $rawMensajes = is_array($data->comprobantes->comprobante->mensajes->mensaje) 
                ? $data->comprobantes->comprobante->mensajes->mensaje 
                : [$data->comprobantes->comprobante->mensajes->mensaje];
            
            foreach ($rawMensajes as $m) {
                $mensajes[] = Mensaje::fromSoap($m);
            }
        }

        return new self(
            (string)($data->estado ?? 'DEVUELTA'),
            $mensajes
        );
    }
}
