<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Dto\AutorizacionResponse;

class AutorizacionResponseTest extends TestCase
{
    /**
     * Regresión: el SRI envuelve la respuesta en RespuestaAutorizacionComprobante.
     * Sin desenvolver ese nodo, un comprobante AUTORIZADO se reportaba como
     * 'ERROR'. Este test bloquea esa regresión.
     */
    public function test_desenvuelve_respuesta_autorizacion_comprobante_wrapper(): void
    {
        $soap = (object) [
            'RespuestaAutorizacionComprobante' => (object) [
                'claveAccesoConsultada' => '1234567890',
                'numeroComprobantes' => '1',
                'autorizaciones' => (object) [
                    'autorizacion' => (object) [
                        'estado' => 'AUTORIZADO',
                        'numeroAutorizacion' => '1234567890',
                        'fechaAutorizacion' => '2026-07-07T16:53:47-05:00',
                        'ambiente' => 'PRUEBAS',
                        'comprobante' => '<factura/>',
                    ],
                ],
            ],
        ];

        $r = AutorizacionResponse::fromSoap($soap);

        $this->assertSame('AUTORIZADO', $r->estado);
        $this->assertSame('1234567890', $r->numeroAutorizacion);
        $this->assertSame('<factura/>', $r->comprobante);
    }

    /**
     * Compatibilidad hacia atrás: si la respuesta ya viene sin el wrapper
     * (estructura antigua), debe seguir funcionando.
     */
    public function test_funciona_sin_wrapper(): void
    {
        $soap = (object) [
            'autorizaciones' => (object) [
                'autorizacion' => (object) [
                    'estado' => 'AUTORIZADO',
                    'numeroAutorizacion' => '999',
                ],
            ],
        ];

        $r = AutorizacionResponse::fromSoap($soap);

        $this->assertSame('AUTORIZADO', $r->estado);
        $this->assertSame('999', $r->numeroAutorizacion);
    }

    /**
     * Sin nodo de autorización (el SRI aún no resolvió o no recibió el
     * comprobante) → estado 'ERROR' sin autorización.
     */
    public function test_sin_autorizacion_devuelve_error(): void
    {
        $soap = (object) [
            'RespuestaAutorizacionComprobante' => (object) [
                'claveAccesoConsultada' => '1234567890',
                'numeroComprobantes' => '0',
                'autorizaciones' => '',
            ],
        ];

        $r = AutorizacionResponse::fromSoap($soap);

        $this->assertSame('ERROR', $r->estado);
        $this->assertNull($r->numeroAutorizacion);
    }
}
