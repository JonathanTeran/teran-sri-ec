<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapClientTransport;
use Teran\Sri\Catalogs2\Ambiente;

class SoapClientTransportTest extends TestCase
{
    public function test_enviar_parses_recibida_from_soap_object(): void
    {
        $recibida = (object) [
            'RespuestaRecepcionComprobante' => (object) ['estado' => 'RECIBIDA', 'comprobantes' => ''],
        ];
        $transport = new SoapClientTransport(soapCaller: fn() => $recibida);

        $outcome = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('RECIBIDA', $outcome->estado);
    }

    public function test_enviar_parses_devuelta_with_messages(): void
    {
        $devuelta = (object) [
            'RespuestaRecepcionComprobante' => (object) [
                'estado' => 'DEVUELTA',
                'comprobantes' => (object) [
                    'comprobante' => (object) [
                        'mensajes' => (object) [
                            'mensaje' => (object) ['identificador' => '43', 'mensaje' => 'RUC inválido', 'tipo' => 'ERROR'],
                        ],
                    ],
                ],
            ],
        ];
        $transport = new SoapClientTransport(soapCaller: fn() => $devuelta);

        $outcome = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('DEVUELTA', $outcome->estado);
        $this->assertCount(1, $outcome->mensajes);
        $this->assertSame('43', $outcome->mensajes[0]->identificador);
    }

    public function test_autorizar_parses_autorizado(): void
    {
        $auth = (object) [
            'autorizaciones' => (object) [
                'autorizacion' => (object) [
                    'estado' => 'AUTORIZADO',
                    'numeroAutorizacion' => '123',
                    'fechaAutorizacion' => '2026-01-26T10:00:00-05:00',
                    'comprobante' => '<factura/>',
                ],
            ],
        ];
        $transport = new SoapClientTransport(soapCaller: fn() => $auth);

        $outcome = $transport->autorizar('2601...819', Ambiente::Pruebas);

        $this->assertSame('AUTORIZADO', $outcome->estado);
        $this->assertSame('123', $outcome->numeroAutorizacion);
    }

    public function test_enviar_devuelta_with_array_of_messages(): void
    {
        $devuelta = (object) [
            'RespuestaRecepcionComprobante' => (object) [
                'estado' => 'DEVUELTA',
                'comprobantes' => (object) [
                    'comprobante' => (object) [
                        'mensajes' => (object) [
                            'mensaje' => [
                                (object) ['identificador' => '43', 'mensaje' => 'RUC inválido', 'tipo' => 'ERROR'],
                                (object) ['identificador' => '45', 'mensaje' => 'Fecha inválida', 'tipo' => 'ERROR'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $transport = new SoapClientTransport(soapCaller: fn() => $devuelta);

        $outcome = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('DEVUELTA', $outcome->estado);
        $this->assertCount(2, $outcome->mensajes);
        $this->assertSame('43', $outcome->mensajes[0]->identificador);
        $this->assertSame('45', $outcome->mensajes[1]->identificador);
    }

    public function test_enviar_codigo70_devuelta_treated_as_recibida(): void
    {
        $codigo70 = (object) [
            'RespuestaRecepcionComprobante' => (object) [
                'estado' => 'DEVUELTA',
                'comprobantes' => (object) [
                    'comprobante' => (object) [
                        'mensajes' => (object) [
                            'mensaje' => (object) ['identificador' => '70', 'mensaje' => 'EN PROCESAMIENTO', 'tipo' => 'INFORMATIVO'],
                        ],
                    ],
                ],
            ],
        ];
        $transport = new SoapClientTransport(soapCaller: fn() => $codigo70);

        $outcome = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('RECIBIDA', $outcome->estado);
    }
}
