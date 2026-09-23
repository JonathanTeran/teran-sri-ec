<?php

declare(strict_types=1);

namespace Teran\Sri\Dto;

class AutorizacionResponse
{
    /** @param Mensaje[] $mensajes */
    public function __construct(
        public readonly string $estado,
        public readonly ?string $numeroAutorizacion,
        public readonly ?string $fechaAutorizacion,
        public readonly ?string $comprobante,
        public readonly array $mensajes = []
    ) {}

    public static function fromSoap(object $data): self
    {
        // El SRI envuelve la respuesta en RespuestaAutorizacionComprobante
        // (igual que recepción con RespuestaRecepcionComprobante). Sin este
        // desenvuelto, autorizaciones->autorizacion nunca se encuentra y todo
        // comprobante AUTORIZADO se reporta erróneamente como 'ERROR'.
        $wrapper = $data->RespuestaAutorizacionComprobante ?? null;
        $root = is_object($wrapper) ? $wrapper : $data;

        $autorizacion = null;
        if (isset($root->autorizaciones->autorizacion)) {
            $autorizacion = is_array($root->autorizaciones->autorizacion)
                ? $root->autorizaciones->autorizacion[0]
                : $root->autorizaciones->autorizacion;
        }

        if (!$autorizacion) {
            return new self('ERROR', null, null, null, []);
        }

        $mensajes = [];
        if (isset($autorizacion->mensajes->mensaje)) {
            $rawMensajes = is_array($autorizacion->mensajes->mensaje) 
                ? $autorizacion->mensajes->mensaje 
                : [$autorizacion->mensajes->mensaje];
            
            foreach ($rawMensajes as $m) {
                $mensajes[] = Mensaje::fromSoap($m);
            }
        }

        return new self(
            (string)($autorizacion->estado ?? 'NO AUTORIZADO'),
            (string)($autorizacion->numeroAutorizacion ?? null),
            (string)($autorizacion->fechaAutorizacion ?? null),
            (string)($autorizacion->comprobante ?? null),
            $mensajes
        );
    }
}
