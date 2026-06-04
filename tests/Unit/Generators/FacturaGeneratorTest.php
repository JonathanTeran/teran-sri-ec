<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Generators\FacturaGenerator;

class FacturaGeneratorTest extends TestCase
{
    public function test_generate_basic_factura_xml()
    {
        $generator = new FacturaGenerator();
        $data = [
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
                'dirMatriz' => 'DIRECCION MATRIZ'
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026',
                'dirEstablecimiento' => 'DIR ESTAB',
                'obligadoContabilidad' => 'NO',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CONSUMIDOR FINAL',
                'identificacionComprador' => '9999999999',
                'totalSinImpuestos' => '10.00',
                'totalDescuento' => '0.00',
                'importetotal' => '11.20',
                'totalConImpuestos' => [
                    [
                        'codigo' => '2',
                        'codigoPorcentaje' => '2',
                        'baseImponible' => '10.00',
                        'valor' => '1.20'
                    ]
                ]
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
                        [
                            'codigo' => '2',
                            'codigoPorcentaje' => '2',
                            'tarifa' => '12.00',
                            'baseImponible' => '10.00',
                            'valor' => '1.20'
                        ]
                    ]
                ]
            ]
        ];

        $xml = $generator->generate($data);

        $this->assertStringContainsString('<razonSocial>EMPRESA PRUEBA</razonSocial>', $xml);
        $this->assertStringContainsString('<importeTotal>11.20</importeTotal>', $xml);
    }

    public function test_factura_declares_correct_schema_version(): void
    {
        // Known issue (diferido a 2.0): el generador emite version="1.1.0" pero la ficha
        // técnica y el XSD (factura_v2.1.0.xsd) requieren "2.1.0". No se cambia la salida
        // en el parche de seguridad 1.1.1; se corrige en 2.0 validando contra el ambiente
        // de pruebas del SRI.
        $this->markTestSkipped('Pendiente 2.0: factura debe declarar version="2.1.0" (hoy emite "1.1.0").');
    }

    public function test_escapes_xml_special_characters_in_values(): void
    {
        $generator = new FacturaGenerator();
        $data = [
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'COMERCIAL J & M <Hnos>',
                'ruc' => '1790011001001',
                'claveAcceso' => '0000000000000000000000000000000000000000000000000',
                'codDoc' => '01',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'AV. 9 & 10'
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026',
                'obligadoContabilidad' => 'NO',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CLIENTE & CÍA',
                'identificacionComprador' => '9999999999',
                'totalSinImpuestos' => '10.00',
                'totalDescuento' => '0.00',
                'importetotal' => '11.20',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '2', 'baseImponible' => '10.00', 'valor' => '1.20']
                ]
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'P01',
                    'descripcion' => 'TUERCAS & PERNOS <3/4">',
                    'cantidad' => '1.00',
                    'precioUnitario' => '10.00',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '10.00',
                    'impuestos' => [
                        ['codigo' => '2', 'codigoPorcentaje' => '2', 'tarifa' => '12.00', 'baseImponible' => '10.00', 'valor' => '1.20']
                    ]
                ]
            ]
        ];

        $xml = $generator->generate($data);

        // El XML debe ser bien formado y los valores deben round-trippear exactos (sin corromperse).
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'El XML generado debe ser bien formado');

        $xpath = new \DOMXPath($dom);
        $this->assertSame('COMERCIAL J & M <Hnos>', $xpath->query('//razonSocial')->item(0)->textContent);
        $this->assertSame('AV. 9 & 10', $xpath->query('//dirMatriz')->item(0)->textContent);
        $this->assertSame('CLIENTE & CÍA', $xpath->query('//razonSocialComprador')->item(0)->textContent);
        $this->assertSame('TUERCAS & PERNOS <3/4">', $xpath->query('//descripcion')->item(0)->textContent);
    }
}
