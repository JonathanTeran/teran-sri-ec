<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Documents\GuiaRemision;
use Teran\Sri\Documents\LiquidacionCompra;
use Teran\Sri\Documents\NotaCredito;
use Teran\Sri\Documents\NotaDebito;
use Teran\Sri\Documents\Retencion;
use Teran\Sri\Emission\EmissionStatus;
use Teran\Sri\Exceptions\ValidationException;
use Teran\Sri\Generators\FacturaGenerator;
use Teran\Sri\Generators\GuiaRemisionGenerator;
use Teran\Sri\Generators\NotaCreditoGenerator;
use Teran\Sri\Generators\NotaDebitoGenerator;
use Teran\Sri\Generators\RetencionGenerator;
use Teran\Sri\Schema\XsdValidator;
use Teran\Sri\Signing\CertificateLoader;
use Teran\Sri\SRI;
use Teran\Sri\SriClient;
use Teran\Sri\Strategies\ComprobanteInterface;
use Teran\Sri\Tests\Support\FakeTransport;
use Teran\Sri\Tests\Support\TestCertificate;
use Teran\Sri\Tests\Support\XmlParity;
use Teran\Sri\Tests\Unit\Xml\FacturaXmlSerializerTest;
use Teran\Sri\Tests\Unit\Xml\GuiaRemisionXmlSerializerTest;
use Teran\Sri\Tests\Unit\Xml\LiquidacionCompraXmlSerializerTest;
use Teran\Sri\Tests\Unit\Xml\NotaCreditoXmlSerializerTest;
use Teran\Sri\Tests\Unit\Xml\NotaDebitoXmlSerializerTest;
use Teran\Sri\Tests\Unit\Xml\RetencionXmlSerializerTest;
use Teran\Sri\Transport\AuthorizationOutcome;
use Teran\Sri\Transport\ReceptionOutcome;
use Teran\Sri\Xml\FacturaXmlSerializer;
use Teran\Sri\Xml\GuiaRemisionXmlSerializer;
use Teran\Sri\Xml\LiquidacionCompraXmlSerializer;
use Teran\Sri\Xml\NotaCreditoXmlSerializer;
use Teran\Sri\Xml\NotaDebitoXmlSerializer;
use Teran\Sri\Xml\RetencionXmlSerializer;

/**
 * Resolución NAC-DGERCGC26-00000027 — Ficha técnica offline v2.34, Anexo 26:
 * <campoAdicional nombre="RUC Proveedor">…</campoAdicional> en los seis tipos de
 * comprobante, igual en la API 1.x (generadores) y en la 2.0 (serializadores).
 */
class RucProveedorComprobantesTest extends TestCase
{
    private const RUC = '1792146739001';

    private const CAMPO = '<campoAdicional nombre="RUC Proveedor">1792146739001</campoAdicional>';

    private const CLAVE = '1002202601179001100100410010010000000041234567814';

    /**
     * Fixtures privados de los tests de cada serializador: los mismos datos
     * que ya prueban la paridad 1.x ↔ 2.0.
     *
     * @param class-string<TestCase> $testClass
     */
    private function fixture(string $testClass, string $method): mixed
    {
        return (new ReflectionMethod($testClass, $method))->invoke(new $testClass('fixture'));
    }

    /**
     * @param class-string<TestCase> $testClass
     * @return array<string, mixed>
     */
    private function arrayFixture(string $testClass): array
    {
        $data = $this->fixture($testClass, 'parityData');
        if (!is_array($data)) {
            $this->fail("{$testClass}::parityData() no devolvió un array.");
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private static function conClavesTexto(array $data): array
    {
        $normalizado = [];
        foreach ($data as $clave => $valor) {
            $normalizado[(string) $clave] = $valor;
        }

        return $normalizado;
    }

    /**
     * @return array<string, array{
     *     0: class-string<TestCase>,
     *     1: \Closure(array<string, mixed>): string,
     *     2: \Closure(array<string, mixed>): string,
     *     3: string
     * }>
     */
    public static function documentosConParidad(): array
    {
        return [
            'nota de crédito' => [
                NotaCreditoXmlSerializerTest::class,
                static fn (array $d): string => (new NotaCreditoGenerator())->generate($d),
                static fn (array $d): string => (new NotaCreditoXmlSerializer())->serialize(NotaCredito::fromArray(self::conClavesTexto($d)), self::CLAVE),
                '04',
            ],
            'nota de débito' => [
                NotaDebitoXmlSerializerTest::class,
                static fn (array $d): string => (new NotaDebitoGenerator())->generate($d),
                static fn (array $d): string => (new NotaDebitoXmlSerializer())->serialize(NotaDebito::fromArray(self::conClavesTexto($d)), self::CLAVE),
                '05',
            ],
            'guía de remisión' => [
                GuiaRemisionXmlSerializerTest::class,
                static fn (array $d): string => (new GuiaRemisionGenerator())->generate($d),
                static fn (array $d): string => (new GuiaRemisionXmlSerializer())->serialize(GuiaRemision::fromArray(self::conClavesTexto($d)), self::CLAVE),
                '06',
            ],
            'retención' => [
                RetencionXmlSerializerTest::class,
                static fn (array $d): string => (new RetencionGenerator())->generate($d),
                static fn (array $d): string => (new RetencionXmlSerializer())->serialize(Retencion::fromArray(self::conClavesTexto($d)), self::CLAVE),
                '07',
            ],
        ];
    }

    /**
     * @dataProvider documentosConParidad
     *
     * @param class-string<TestCase> $testClass
     * @param \Closure(array<string, mixed>): string $legacyXml
     * @param \Closure(array<string, mixed>): string $modernXml
     */
    public function test_both_apis_emit_the_provider_field_with_identical_structure(
        string $testClass,
        \Closure $legacyXml,
        \Closure $modernXml,
        string $codDoc,
    ): void {
        $data = $this->arrayFixture($testClass);
        $data['infoAdicional'] = ['Email' => 'cliente@example.com'];
        $data['rucProveedor'] = self::RUC;

        $infoTributaria = $data['infoTributaria'] ?? null;
        if (!is_array($infoTributaria)) {
            $this->fail('El fixture no trae infoTributaria.');
        }
        $infoTributaria['claveAcceso'] = self::CLAVE;
        $infoTributaria['codDoc'] = $codDoc;

        $data1x = $data;
        $data1x['infoTributaria'] = $infoTributaria;

        $legacy = $legacyXml($data1x);
        $modern = $modernXml($data);

        $this->assertStringContainsString(self::CAMPO, $legacy);
        $this->assertStringContainsString(self::CAMPO, $modern);
        XmlParity::assertSameStructure($legacy, $modern, $this);
    }

    public function test_factura_serializer_adds_the_field_and_stays_valid_against_the_official_xsd(): void
    {
        $xml = (new FacturaXmlSerializer())->serialize($this->factura(), self::CLAVE, self::RUC);

        $this->assertStringContainsString(self::CAMPO, $xml);
        $this->assertTrue(XsdValidator::validate($xml, __DIR__.'/../../resources/xsd/factura_v2.1.0.xsd'));
    }

    public function test_liquidacion_serializer_adds_the_field_and_stays_valid_against_the_official_xsd(): void
    {
        $liquidacion = $this->fixture(LiquidacionCompraXmlSerializerTest::class, 'liquidacion');
        if (!$liquidacion instanceof LiquidacionCompra) {
            $this->fail('Fixture de liquidación inválido.');
        }
        $xml = (new LiquidacionCompraXmlSerializer())->serialize($liquidacion, self::CLAVE, self::RUC);

        $this->assertStringContainsString(self::CAMPO, $xml);
        $this->assertTrue(XsdValidator::validate($xml, __DIR__.'/../../resources/xsd/liquidacionCompra_v1.1.0.xsd'));
    }

    public function test_without_provider_ruc_the_output_is_unchanged(): void
    {
        $this->assertStringNotContainsString('infoAdicional', (new FacturaXmlSerializer())->serialize($this->factura(), self::CLAVE));
    }

    public function test_legacy_factura_generator_emits_the_field_even_without_other_additional_fields(): void
    {
        $xml = (new FacturaGenerator())->generate($this->legacyFactura() + ['rucProveedor' => self::RUC]);

        $this->assertStringContainsString('<infoAdicional>', $xml);
        $this->assertStringContainsString(self::CAMPO, $xml);
    }

    public function test_sri_facade_injects_the_configured_ruc_into_every_voucher(): void
    {
        $sri = new class ('pruebas') extends SRI {
            public ?string $capturedXml = null;

            /** @return array<string, mixed> */
            public function procesar(ComprobanteInterface $comprobante): array
            {
                $this->capturedXml = $comprobante->generarXml();

                return [];
            }
        };

        $sri->setRucProveedor('1792146739-001');
        $this->assertSame(self::RUC, $sri->getRucProveedor());

        $data = $this->legacyFactura();
        $sri->facturaFromArray($data);

        $this->assertStringContainsString(self::CAMPO, (string) $sri->capturedXml);

        $sri->setRucProveedor(null);
        $sri->facturaFromArray($data);
        $this->assertStringNotContainsString('RUC Proveedor', (string) $sri->capturedXml);
    }

    public function test_sri_facade_rejects_an_invalid_provider_ruc(): void
    {
        $this->expectException(ValidationException::class);
        (new SRI('pruebas'))->setRucProveedor('12345');
    }

    public function test_sri_client_adds_the_field_to_the_signed_xml_it_sends(): void
    {
        $transport = new FakeTransport(
            new ReceptionOutcome('RECIBIDA', []),
            new AuthorizationOutcome('AUTORIZADO', '1234567890', '2026-09-26T10:00:00-05:00', '<auth/>', []),
        );
        $tc = TestCertificate::modernP12();
        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);
        $client = SriClient::create(Ambiente::Pruebas, $cert, $transport, self::RUC);

        $result = $client->emit($this->factura(), '2601202601179001100100110010010000000011234567819');

        $this->assertSame(EmissionStatus::Authorized, $result->status);
        $this->assertStringContainsString(self::CAMPO, (string) $transport->lastSentXml);
    }

    public function test_sri_client_rejects_an_invalid_provider_ruc(): void
    {
        $tc = TestCertificate::modernP12();
        $cert = (new CertificateLoader())->load($tc['p12'], $tc['password']);

        $this->expectException(ValidationException::class);
        SriClient::create(Ambiente::Pruebas, $cert, null, '999');
    }

    private function factura(): Factura
    {
        $factura = $this->fixture(FacturaXmlSerializerTest::class, 'factura');
        if (!$factura instanceof Factura) {
            $this->fail('Fixture de factura inválido.');
        }

        return $factura;
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyFactura(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'EMPRESA PRUEBA',
                'ruc' => '1790011001001',
                'claveAcceso' => '0000000000000000000000000000000000000000000000000',
                'codDoc' => '01',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'DIRECCION MATRIZ',
            ],
            'infoFactura' => [
                'fechaEmision' => '26/09/2026',
                'dirEstablecimiento' => 'DIR ESTAB',
                'obligadoContabilidad' => 'NO',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CLIENTE',
                'identificacionComprador' => '1713175071',
                'totalSinImpuestos' => '10.00',
                'totalDescuento' => '0.00',
                'importetotal' => '11.50',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '10.00', 'valor' => '1.50'],
                ],
                'pagos' => [['formaPago' => '01', 'total' => '11.50']],
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'P01',
                    'descripcion' => 'PRODUCTO',
                    'cantidad' => '1.00',
                    'precioUnitario' => '10.00',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '10.00',
                    'impuestos' => [
                        ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '10.00', 'valor' => '1.50'],
                    ],
                ],
            ],
        ];
    }
}
