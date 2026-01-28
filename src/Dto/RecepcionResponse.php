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
        $estado = 'DEVUELTA';
        $mensajes = [];
        
        // Handle the wrapper property if it exists (SRI SOAP often wraps it)
        $root = $data->RespuestaRecepcionComprobante ?? $data;

        if (isset($root->estado)) {
            $estado = (string)$root->estado;
        }

        // Traverse: comprobantes -> comprobante -> mensajes -> mensaje
        if (isset($root->comprobantes->comprobante->mensajes->mensaje)) {
             $rawMensajes = $root->comprobantes->comprobante->mensajes->mensaje;
             
             // Normalize to array
             if (!is_array($rawMensajes)) {
                 $rawMensajes = [$rawMensajes];
             }
             
             foreach ($rawMensajes as $m) {
                 $mensajes[] = Mensaje::fromSoap($m);
             }
        }
        
        // Check if DEVUELTA is actually "EN PROCESAMIENTO" (not a real error)
        if ($estado === 'DEVUELTA') {
            foreach ($mensajes as $mensaje) {
                // Code 70 = EN PROCESAMIENTO
                if ($mensaje->identificador == '70' || 
                    stripos($mensaje->mensaje, 'PROCESAMIENTO') !== false) {
                    // Treat as RECIBIDA since it's just pending, not rejected
                    $estado = 'RECIBIDA';
                    break;
                }
            }
        }

        return new self($estado, $mensajes);
    }
}
