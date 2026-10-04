<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Teran\Sri\Support\MensajeSri;

class MensajeSriTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fallasConocidas(): iterable
    {
        yield 'SoapClient sin conexión' => ['Could not connect to host', 'no se pudo conectar con el servidor del SRI'];
        yield 'conexión rechazada' => ['Connection refused', 'el servidor del SRI rechazó la conexión'];
        yield 'cabeceras cortadas' => ['Error Fetching http headers', 'el servidor del SRI cortó la conexión sin responder'];
        yield 'WSDL inaccesible' => [
            "SOAP-ERROR: Parsing WSDL: Couldn't load from 'https://cel.sri.gob.ec/...?wsdl' : failed to load external entity",
            'el SRI no entregó la definición del servicio (WSDL); el servicio está caído o inaccesible',
        ];
        yield 'DNS' => ['php_network_getaddresses: getaddrinfo failed: Name or service not known', 'no se pudo resolver el dominio del SRI (fallo de DNS)'];
        yield 'timeout' => ['Operation timed out after 30000 milliseconds', 'el servidor del SRI no respondió a tiempo'];
        yield 'SSL' => ['SSL: no alternative certificate subject name matches target host name', 'falló la conexión segura (SSL/TLS) con el servidor del SRI'];
        yield '503' => ['Service Unavailable', 'el servicio del SRI está fuera de línea (503)'];
        yield 'HTML en vez de XML' => ['looks like we got no XML document', 'el SRI respondió con algo que no es XML (suele ser una página de mantenimiento)'];
    }

    #[DataProvider('fallasConocidas')]
    public function test_traduce_las_fallas_conocidas(string $original, string $esperado): void
    {
        $this->assertSame($esperado, MensajeSri::deFalla($original));
    }

    public function test_conserva_el_texto_cuando_no_lo_reconoce(): void
    {
        $this->assertSame('el SRI respondió: Algo rarísimo pasó', MensajeSri::deFalla('Algo rarísimo pasó'));
    }

    public function test_mensaje_vacio(): void
    {
        $this->assertSame('el servidor del SRI no respondió', MensajeSri::deFalla('   '));
    }

    public function test_mensaje_completo_tras_reintentos(): void
    {
        $this->assertSame(
            'Error de comunicación con el SRI tras 3 intentos: no se pudo conectar con el servidor del SRI.',
            MensajeSri::trasReintentos(3, 'Could not connect to host'),
        );
        $this->assertSame(
            'Error de comunicación con el SRI tras 1 intento: el servidor del SRI no respondió a tiempo.',
            MensajeSri::trasReintentos(1, 'Connection timed out'),
        );
    }
}
