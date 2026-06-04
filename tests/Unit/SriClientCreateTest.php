<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teran\Sri\SriClient;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\Transport\SoapClientTransport;
use Teran\Sri\Tests\Support\TestCertificate;

class SriClientCreateTest extends TestCase
{
    public function test_create_builds_a_working_client(): void
    {
        $tc   = TestCertificate::modernP12();
        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        // Transporte explícito (fake) para no tocar la red.
        $transport = new SoapClientTransport(soapCaller: fn() => (object) [
            'RespuestaRecepcionComprobante' => (object) ['estado' => 'RECIBIDA', 'comprobantes' => ''],
        ]);

        $client = SriClient::create(Ambiente::Pruebas, $cert, $transport);

        $this->assertInstanceOf(SriClient::class, $client);
    }

    public function test_create_defaults_to_soap_client_transport(): void
    {
        $tc   = TestCertificate::modernP12();
        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        $client = SriClient::create(Ambiente::Pruebas, $cert);
        $this->assertInstanceOf(SriClient::class, $client);
    }
}
