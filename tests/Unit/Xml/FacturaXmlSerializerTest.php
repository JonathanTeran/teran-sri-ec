<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Xml\FacturaXmlSerializer;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Schema\XsdValidator;

class FacturaXmlSerializerTest extends TestCase
{
    private function factura(): Factura
    {
        return Factura::fromArray([
            'infoTributaria' => [
                'ambiente' => '1',
                'razonSocial' => 'COMERCIAL J & M',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'Quito',
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CONSUMIDOR FINAL',
                'identificacionComprador' => '9999999999',
                'totalSinImpuestos' => '100.00',
                'totalDescuento' => '0.00',
                'importeTotal' => '115.00',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00'],
                ],
                'pagos' => [
                    ['formaPago' => '01', 'total' => '115.00'],
                ],
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'PROD001',
                    'descripcion' => 'Tornillos & tuercas',
                    'cantidad' => '1.00',
                    'precioUnitario' => '100.00',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '100.00',
                    'impuestos' => [
                        ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
                    ],
                ],
            ],
        ]);
    }

    public function test_serializes_header_and_clave_acceso(): void
    {
        $clave = '2601202601179001100100110010010000000011234567819';
        $xml = (new FacturaXmlSerializer())->serialize($this->factura(), $clave);

        $this->assertStringContainsString('<factura id="comprobante" version="2.1.0">', $xml);
        $this->assertStringContainsString("<claveAcceso>$clave</claveAcceso>", $xml);
        $this->assertStringContainsString('<codDoc>01</codDoc>', $xml);
        $this->assertStringContainsString('<ambiente>1</ambiente>', $xml);
    }

    public function test_escapes_values_and_formats_decimals(): void
    {
        $xml = (new FacturaXmlSerializer())->serialize($this->factura(), '0000000000000000000000000000000000000000000000000');

        // El & se reparsea correctamente (bien formado).
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('COMERCIAL J & M', $dom->getElementsByTagName('razonSocial')->item(0)->textContent);
        $this->assertSame('Tornillos & tuercas', $dom->getElementsByTagName('descripcion')->item(0)->textContent);

        // Decimales SRI: 6 para cantidad/precioUnitario, 2 para montos.
        $this->assertStringContainsString('<cantidad>1.000000</cantidad>', $xml);
        $this->assertStringContainsString('<precioUnitario>100.000000</precioUnitario>', $xml);
        $this->assertStringContainsString('<importeTotal>115.00</importeTotal>', $xml);
        $this->assertStringContainsString('<formaPago>01</formaPago>', $xml);
    }

    public function test_is_deterministic(): void
    {
        $f = $this->factura();
        $s = new FacturaXmlSerializer();
        $this->assertSame(
            $s->serialize($f, '0000000000000000000000000000000000000000000000000'),
            $s->serialize($f, '0000000000000000000000000000000000000000000000000')
        );
    }

    public function test_serialized_xml_is_valid_against_official_xsd(): void
    {
        $clave = '2601202601179001100100110010010000000011234567819';
        $xml = (new FacturaXmlSerializer())->serialize($this->factura(), $clave);

        $xsdPath = __DIR__ . '/../../../resources/xsd/factura_v2.1.0.xsd';
        $this->assertFileExists($xsdPath, 'Debe existir el XSD oficial de factura.');

        // XsdValidator::validate lanza ValidationException si no cumple; true si cumple.
        $this->assertTrue(XsdValidator::validate($xml, $xsdPath));
    }
}
