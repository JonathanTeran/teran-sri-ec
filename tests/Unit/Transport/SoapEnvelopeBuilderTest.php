<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Transport\SoapEnvelopeBuilder;

class SoapEnvelopeBuilderTest extends TestCase
{
    public function test_reception_envelope_base64_encodes_signed_xml(): void
    {
        $signed = '<factura>áé&</factura>';
        $env = (new SoapEnvelopeBuilder())->reception($signed);

        $this->assertStringContainsString('http://ec.gob.sri.ws.recepcion', $env);
        $this->assertStringContainsString('validarComprobante', $env);
        $this->assertStringContainsString('<xml>' . base64_encode($signed) . '</xml>', $env);
        // Bien formado
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($env));
    }

    public function test_authorization_envelope_carries_clave(): void
    {
        $env = (new SoapEnvelopeBuilder())->authorization('2601...819');

        $this->assertStringContainsString('http://ec.gob.sri.ws.autorizacion', $env);
        $this->assertStringContainsString('autorizacionComprobante', $env);
        $this->assertStringContainsString('<claveAccesoComprobante>2601...819</claveAccesoComprobante>', $env);
    }
}
