<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Generators\LiquidacionCompraGenerator;
use Teran\Sri\Schema\XsdValidator;

class LiquidacionCompraGeneratorTest extends TestCase
{
    private function validData(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'EMPRESA PRUEBA',
                'ruc' => '1790011001001',
                'claveAcceso' => str_repeat('0', 49),
                'codDoc' => '03',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'DIRECCION MATRIZ',
            ],
            'infoLiquidacionCompra' => [
                'fechaEmision' => '26/01/2026',
                'dirEstablecimiento' => 'DIR ESTAB',
                'obligadoContabilidad' => 'NO',
                'tipoIdentificacionProveedor' => '05',
                'razonSocialProveedor' => 'PROVEEDOR NATURAL',
                'identificacionProveedor' => '1712345678',
                'direccionProveedor' => 'AV. AMAZONAS N23-45',
                'totalSinImpuestos' => '100.00',
                'totalDescuento' => '0.00',
                'importeTotal' => '115.00',
                'totalConImpuestos' => [
                    [
                        'codigo' => '2',
                        'codigoPorcentaje' => '4',
                        'baseImponible' => '100.00',
                        'valor' => '15.00',
                    ],
                ],
                'pagos' => [
                    ['formaPago' => '01', 'total' => '115.00'],
                ],
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'SERV001',
                    'descripcion' => 'SERVICIO PRESTADO',
                    'unidadMedida' => 'UNIDAD',
                    'cantidad' => '1.00',
                    'precioUnitario' => '100.00',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '100.00',
                    'impuestos' => [
                        [
                            'codigo' => '2',
                            'codigoPorcentaje' => '4',
                            'tarifa' => '15.00',
                            'baseImponible' => '100.00',
                            'valor' => '15.00',
                        ],
                    ],
                ],
            ],
            'infoAdicional' => [
                'email' => 'proveedor@example.com',
            ],
        ];
    }

    public function test_generate_basic_liquidacion_xml(): void
    {
        $xml = (new LiquidacionCompraGenerator())->generate($this->validData());

        $this->assertStringContainsString('<liquidacionCompra id="comprobante" version="1.1.0">', $xml);
        $this->assertStringContainsString('<codDoc>03</codDoc>', $xml);
        $this->assertStringContainsString('<razonSocialProveedor>PROVEEDOR NATURAL</razonSocialProveedor>', $xml);
        $this->assertStringContainsString('<identificacionProveedor>1712345678</identificacionProveedor>', $xml);
        $this->assertStringContainsString('<importeTotal>115.00</importeTotal>', $xml);
        $this->assertStringContainsString('<moneda>DOLAR</moneda>', $xml);

        // Orden estricto del XSD: proveedor antes de los totales, totales antes de pagos.
        $this->assertLessThan(strpos($xml, '<razonSocialProveedor>'), strpos($xml, '<tipoIdentificacionProveedor>'));
        $this->assertLessThan(strpos($xml, '<totalSinImpuestos>'), strpos($xml, '<direccionProveedor>'));
        $this->assertLessThan(strpos($xml, '<importeTotal>'), strpos($xml, '<totalConImpuestos>'));
        $this->assertLessThan(strpos($xml, '<pagos>'), strpos($xml, '<moneda>'));
    }

    public function test_generated_xml_is_valid_against_official_xsd(): void
    {
        $xml = (new LiquidacionCompraGenerator())->generate($this->validData());

        $xsdPath = __DIR__ . '/../../../resources/xsd/liquidacionCompra_v1.1.0.xsd';
        $this->assertFileExists($xsdPath, 'Debe existir el XSD oficial de liquidación de compra.');
        $this->assertTrue(XsdValidator::validate($xml, $xsdPath));
    }

    public function test_escapes_xml_special_characters_in_values(): void
    {
        $data = $this->validData();
        $data['infoLiquidacionCompra']['razonSocialProveedor'] = 'PROVEEDOR J & M <Hnos>';
        $data['detalles'][0]['descripcion'] = 'TUERCAS & PERNOS <3/4">';

        $xml = (new LiquidacionCompraGenerator())->generate($data);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'El XML generado debe ser bien formado');

        $xpath = new \DOMXPath($dom);
        $this->assertSame('PROVEEDOR J & M <Hnos>', $xpath->query('//razonSocialProveedor')->item(0)->textContent);
        $this->assertSame('TUERCAS & PERNOS <3/4">', $xpath->query('//descripcion')->item(0)->textContent);
    }
}
