<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Xml\LiquidacionCompraXmlSerializer;
use Teran\Sri\Documents\LiquidacionCompra;
use Teran\Sri\Schema\XsdValidator;

class LiquidacionCompraXmlSerializerTest extends TestCase
{
    private function liquidacion(): LiquidacionCompra
    {
        return LiquidacionCompra::fromArray([
            'infoTributaria' => [
                'ambiente' => '1',
                'razonSocial' => 'COMERCIAL J & M',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'Quito',
            ],
            'infoLiquidacionCompra' => [
                'fechaEmision' => '26/01/2026',
                'tipoIdentificacionProveedor' => '05',
                'razonSocialProveedor' => 'PROVEEDOR & CIA',
                'identificacionProveedor' => '1712345678',
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
                    'codigoPrincipal' => 'SERV001',
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
        $clave = '2601202603179001100100110010010000000011234567819';
        $xml = (new LiquidacionCompraXmlSerializer())->serialize($this->liquidacion(), $clave);

        $this->assertStringContainsString('<liquidacionCompra id="comprobante" version="1.1.0">', $xml);
        $this->assertStringContainsString("<claveAcceso>$clave</claveAcceso>", $xml);
        $this->assertStringContainsString('<codDoc>03</codDoc>', $xml);
        $this->assertStringContainsString('<ambiente>1</ambiente>', $xml);
    }

    public function test_escapes_values_and_formats_decimals(): void
    {
        $xml = (new LiquidacionCompraXmlSerializer())->serialize($this->liquidacion(), str_repeat('0', 49));

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('COMERCIAL J & M', $dom->getElementsByTagName('razonSocial')->item(0)->textContent);
        $this->assertSame('PROVEEDOR & CIA', $dom->getElementsByTagName('razonSocialProveedor')->item(0)->textContent);
        $this->assertSame('Tornillos & tuercas', $dom->getElementsByTagName('descripcion')->item(0)->textContent);

        // Decimales SRI: 6 para cantidad/precioUnitario, 2 para montos.
        $this->assertStringContainsString('<cantidad>1.000000</cantidad>', $xml);
        $this->assertStringContainsString('<precioUnitario>100.000000</precioUnitario>', $xml);
        $this->assertStringContainsString('<importeTotal>115.00</importeTotal>', $xml);
        $this->assertStringContainsString('<formaPago>01</formaPago>', $xml);
    }

    public function test_is_deterministic(): void
    {
        $l = $this->liquidacion();
        $s = new LiquidacionCompraXmlSerializer();
        $this->assertSame(
            $s->serialize($l, str_repeat('0', 49)),
            $s->serialize($l, str_repeat('0', 49))
        );
    }

    public function test_serialized_xml_is_valid_against_official_xsd(): void
    {
        $clave = '2601202603179001100100110010010000000011234567819';
        $xml = (new LiquidacionCompraXmlSerializer())->serialize($this->liquidacion(), $clave);

        $xsdPath = __DIR__ . '/../../../resources/xsd/liquidacionCompra_v1.1.0.xsd';
        $this->assertFileExists($xsdPath, 'Debe existir el XSD oficial de liquidación de compra.');
        $this->assertTrue(XsdValidator::validate($xml, $xsdPath));
    }
}
