<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapStdClassParser;

class SoapStdClassParserTest extends TestCase
{
    public function test_parses_recibida(): void
    {
        $resp = (object) ['RespuestaRecepcionComprobante' => (object) ['estado' => 'RECIBIDA', 'comprobantes' => '']];
        $o = (new SoapStdClassParser())->reception($resp);
        $this->assertSame('RECIBIDA', $o->estado);
        $this->assertSame([], $o->mensajes);
    }

    public function test_parses_devuelta_with_single_message(): void
    {
        $resp = (object) ['RespuestaRecepcionComprobante' => (object) [
            'estado' => 'DEVUELTA',
            'comprobantes' => (object) ['comprobante' => (object) ['mensajes' => (object) ['mensaje' => (object) [
                'identificador' => '43', 'mensaje' => 'RUC inválido', 'tipo' => 'ERROR', 'informacionAdicional' => 'x',
            ]]]],
        ]];
        $o = (new SoapStdClassParser())->reception($resp);
        $this->assertSame('DEVUELTA', $o->estado);
        $this->assertCount(1, $o->mensajes);
        $this->assertSame('43', $o->mensajes[0]->identificador);
    }

    public function test_parses_autorizado(): void
    {
        $resp = (object) ['autorizaciones' => (object) ['autorizacion' => (object) [
            'estado' => 'AUTORIZADO', 'numeroAutorizacion' => '123', 'fechaAutorizacion' => '2026-01-26', 'comprobante' => '<f/>', 'mensajes' => '',
        ]]];
        $o = (new SoapStdClassParser())->authorization($resp);
        $this->assertSame('AUTORIZADO', $o->estado);
        $this->assertSame('123', $o->numeroAutorizacion);
        $this->assertSame('<f/>', $o->comprobante);
    }

    public function test_authorization_not_yet_processed_is_en_proceso_not_rejected(): void
    {
        // Justo tras RECIBIDA el SRI puede responder sin nodo <autorizacion> (aún procesando).
        // Eso NO es un rechazo: debe tratarse como EN PROCESO (reintentar), no NO AUTORIZADO.
        $resp = (object) ['claveAccesoConsultada' => 'x', 'numeroComprobantes' => '0', 'autorizaciones' => ''];
        $o = (new SoapStdClassParser())->authorization($resp);
        $this->assertSame('EN PROCESO', $o->estado);
        $this->assertNull($o->numeroAutorizacion);
    }
}
