<?php

declare(strict_types=1);

namespace Teran\Sri\Transport;

use Teran\Sri\Emission\Message;

/** Parsea la respuesta stdClass de SoapClient del SRI a outcomes tipados (port de los DTOs 1.x). */
final class SoapStdClassParser
{
    public function reception(\stdClass $data): ReceptionOutcome
    {
        /** @var \stdClass $root */
        $root = isset($data->RespuestaRecepcionComprobante) && $data->RespuestaRecepcionComprobante instanceof \stdClass
            ? $data->RespuestaRecepcionComprobante
            : $data;
        $estado = isset($root->estado) && (is_string($root->estado) || is_numeric($root->estado))
            ? (string) $root->estado
            : 'DEVUELTA';

        $mensajes = [];
        if (
            isset($root->comprobantes)
            && $root->comprobantes instanceof \stdClass
            && isset($root->comprobantes->comprobante)
            && $root->comprobantes->comprobante instanceof \stdClass
            && isset($root->comprobantes->comprobante->mensajes)
            && $root->comprobantes->comprobante->mensajes instanceof \stdClass
            && isset($root->comprobantes->comprobante->mensajes->mensaje)
        ) {
            $mensajes = $this->messages($root->comprobantes->comprobante->mensajes->mensaje);
        }
        return new ReceptionOutcome($estado, $mensajes);
    }

    public function authorization(\stdClass $data): AuthorizationOutcome
    {
        $autorizacion = null;
        if (isset($data->autorizaciones) && $data->autorizaciones instanceof \stdClass && isset($data->autorizaciones->autorizacion)) {
            $raw = $data->autorizaciones->autorizacion;
            $autorizacion = is_array($raw)
                ? ($raw[0] ?? null)
                : $raw;
        }
        if ($autorizacion === null) {
            return new AuthorizationOutcome('EN PROCESO');
        }

        if (!$autorizacion instanceof \stdClass) {
            return new AuthorizationOutcome('EN PROCESO');
        }

        $mensajes = (
            isset($autorizacion->mensajes)
            && $autorizacion->mensajes instanceof \stdClass
            && isset($autorizacion->mensajes->mensaje)
        ) ? $this->messages($autorizacion->mensajes->mensaje) : [];

        return new AuthorizationOutcome(
            estado: isset($autorizacion->estado) && (is_string($autorizacion->estado) || is_numeric($autorizacion->estado))
                ? (string) $autorizacion->estado
                : 'EN PROCESO',
            numeroAutorizacion: isset($autorizacion->numeroAutorizacion) && (is_string($autorizacion->numeroAutorizacion) || is_numeric($autorizacion->numeroAutorizacion))
                ? (string) $autorizacion->numeroAutorizacion
                : null,
            fechaAutorizacion: isset($autorizacion->fechaAutorizacion) && (is_string($autorizacion->fechaAutorizacion) || is_numeric($autorizacion->fechaAutorizacion))
                ? (string) $autorizacion->fechaAutorizacion
                : null,
            comprobante: isset($autorizacion->comprobante) && (is_string($autorizacion->comprobante) || is_numeric($autorizacion->comprobante))
                ? (string) $autorizacion->comprobante
                : null,
            mensajes: $mensajes,
        );
    }

    /** @return Message[] */
    private function messages(mixed $raw): array
    {
        $rows = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($rows as $m) {
            if (!$m instanceof \stdClass) {
                continue;
            }
            $out[] = new Message(
                identificador: isset($m->identificador) && (is_string($m->identificador) || is_numeric($m->identificador)) ? (string) $m->identificador : '',
                mensaje: isset($m->mensaje) && (is_string($m->mensaje) || is_numeric($m->mensaje)) ? (string) $m->mensaje : '',
                tipo: isset($m->tipo) && (is_string($m->tipo) || is_numeric($m->tipo)) ? (string) $m->tipo : '',
                informacionAdicional: isset($m->informacionAdicional) && (is_string($m->informacionAdicional) || is_numeric($m->informacionAdicional)) ? (string) $m->informacionAdicional : '',
            );
        }
        return $out;
    }
}
