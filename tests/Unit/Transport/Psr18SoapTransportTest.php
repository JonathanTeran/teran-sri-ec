<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\Psr18SoapTransport;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Exceptions\CommunicationException;
use Teran\Sri\Tests\Support\FakePsr18Client;
use Teran\Sri\Tests\Support\ThrowingPsr18Client;
use Nyholm\Psr7\Factory\Psr17Factory;

class Psr18SoapTransportTest extends TestCase
{
    public function test_enviar_posts_to_reception_endpoint_and_parses(): void
    {
        $body = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><r><RespuestaRecepcionComprobante><estado>RECIBIDA</estado></RespuestaRecepcionComprobante></r></soap:Body></soap:Envelope>';
        $client = new FakePsr18Client($body);
        $factory = new Psr17Factory();
        $transport = new Psr18SoapTransport($client, $factory, $factory);

        $outcome = $transport->enviar('<factura/>', Ambiente::Pruebas);

        $this->assertSame('RECIBIDA', $outcome->estado);
        $this->assertSame('POST', $client->lastRequest->getMethod());
        $this->assertStringContainsString('celcer.sri.gob.ec', (string) $client->lastRequest->getUri());
        $this->assertStringContainsString('RecepcionComprobantesOffline', (string) $client->lastRequest->getUri());
    }

    public function test_autorizar_uses_production_endpoint_for_produccion(): void
    {
        $body = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><RespuestaAutorizacionComprobante><autorizaciones><autorizacion><estado>AUTORIZADO</estado><numeroAutorizacion>123</numeroAutorizacion></autorizacion></autorizaciones></RespuestaAutorizacionComprobante></soap:Body></soap:Envelope>';
        $client = new FakePsr18Client($body);
        $factory = new Psr17Factory();
        $transport = new Psr18SoapTransport($client, $factory, $factory);

        $outcome = $transport->autorizar('2601...819', Ambiente::Produccion);

        $this->assertSame('AUTORIZADO', $outcome->estado);
        $this->assertSame('123', $outcome->numeroAutorizacion);
        $this->assertStringContainsString('cel.sri.gob.ec', (string) $client->lastRequest->getUri());
        $this->assertStringNotContainsString('celcer', (string) $client->lastRequest->getUri());
    }

    public function test_enviar_throws_communication_exception_on_http_500(): void
    {
        $client = new FakePsr18Client('Internal Server Error', 500);
        $factory = new Psr17Factory();
        $transport = new Psr18SoapTransport($client, $factory, $factory);

        $this->expectException(CommunicationException::class);
        $this->expectExceptionMessage('El SRI respondió HTTP 500');
        $transport->enviar('<factura/>', Ambiente::Pruebas);
    }

    public function test_enviar_throws_communication_exception_when_client_throws(): void
    {
        $factory = new Psr17Factory();
        $transport = new Psr18SoapTransport(new ThrowingPsr18Client(), $factory, $factory);

        $this->expectException(CommunicationException::class);
        $this->expectExceptionMessage('Error de comunicación con el SRI');
        $transport->enviar('<factura/>', Ambiente::Pruebas);
    }
}
