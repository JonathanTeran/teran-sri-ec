<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;

/** Parsea la respuesta stdClass de SoapClient del SRI a outcomes tipados (port de los DTOs 1.x). */
final class SoapStdClassParser
{
    public function reception(object $data): ReceptionOutcome
    {
        $root = $data->RespuestaRecepcionComprobante ?? $data;
        $estado = isset($root->estado) ? (string) $root->estado : 'DEVUELTA';

        $mensajes = [];
        if (isset($root->comprobantes->comprobante->mensajes->mensaje)) {
            $mensajes = $this->messages($root->comprobantes->comprobante->mensajes->mensaje);
        }
        return new ReceptionOutcome($estado, $mensajes);
    }

    public function authorization(object $data): AuthorizationOutcome
    {
        $autorizacion = null;
        if (isset($data->autorizaciones->autorizacion)) {
            $autorizacion = is_array($data->autorizaciones->autorizacion)
                ? ($data->autorizaciones->autorizacion[0] ?? null)
                : $data->autorizaciones->autorizacion;
        }
        if ($autorizacion === null) {
            return new AuthorizationOutcome('NO AUTORIZADO');
        }

        $mensajes = isset($autorizacion->mensajes->mensaje) ? $this->messages($autorizacion->mensajes->mensaje) : [];

        return new AuthorizationOutcome(
            estado: (string) ($autorizacion->estado ?? 'NO AUTORIZADO'),
            numeroAutorizacion: isset($autorizacion->numeroAutorizacion) ? (string) $autorizacion->numeroAutorizacion : null,
            fechaAutorizacion: isset($autorizacion->fechaAutorizacion) ? (string) $autorizacion->fechaAutorizacion : null,
            comprobante: isset($autorizacion->comprobante) ? (string) $autorizacion->comprobante : null,
            mensajes: $mensajes,
        );
    }

    /** @return Message[] */
    private function messages(mixed $raw): array
    {
        $rows = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($rows as $m) {
            $out[] = new Message(
                identificador: isset($m->identificador) ? (string) $m->identificador : '',
                mensaje: isset($m->mensaje) ? (string) $m->mensaje : '',
                tipo: isset($m->tipo) ? (string) $m->tipo : '',
                informacionAdicional: isset($m->informacionAdicional) ? (string) $m->informacionAdicional : '',
            );
        }
        return $out;
    }
}
