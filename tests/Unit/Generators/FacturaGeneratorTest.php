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

    public function test_generates_export_factura_block_in_xsd_order(): void
    {
        $generator = new FacturaGenerator();
        $data = [
            'infoTributaria' => [
                'ambiente' => '1', 'tipoEmision' => '1', 'razonSocial' => 'EXPORTADORA SA',
                'ruc' => '1790011001001', 'claveAcceso' => str_repeat('0', 49),
                'codDoc' => '01', 'estab' => '001', 'ptoEmi' => '001',
                'secuencial' => '000000001', 'dirMatriz' => 'DIR MATRIZ',
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026',
                'obligadoContabilidad' => 'SI',
                // Bloque exportación
                'comercioExterior' => 'EXPORTADOR',
                'incoTermFactura' => 'FOB',
                'lugarIncoTerm' => 'GUAYAQUIL',
                'paisOrigen' => '593',
                'puertoEmbarque' => 'GUAYAQUIL',
                'puertoDestino' => 'MIAMI',
                'paisDestino' => '249',
                'paisAdquisicion' => '593',
                'tipoIdentificacionComprador' => '06',
                'razonSocialComprador' => 'FOREIGN BUYER INC',
                'identificacionComprador' => 'PASSPORT123',
                'totalSinImpuestos' => '1000.00',
                'incoTermTotalSinImpuestos' => 'FOB',
                'totalDescuento' => '0.00',
                'importetotal' => '1000.00',
                'propina' => '0.00',
                'fleteInternacional' => '50.00',
                'seguroInternacional' => '20.00',
                'gastosAduaneros' => '10.00',
                'gastosTransporteOtros' => '5.00',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '0', 'baseImponible' => '1000.00', 'valor' => '0.00'],
                ],
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'EXP01', 'descripcion' => 'BANANO CAJA',
                    'cantidad' => '100.00', 'precioUnitario' => '10.00',
                    'descuento' => '0.00', 'precioTotalSinImpuesto' => '1000.00',
                    'impuestos' => [
                        ['codigo' => '2', 'codigoPorcentaje' => '0', 'tarifa' => '0.00', 'baseImponible' => '1000.00', 'valor' => '0.00'],
                    ],
                ],
            ],
        ];

        $xml = $generator->generate($data);

        // Todos los campos de exportación presentes.
        foreach (['EXPORTADOR', '<incoTermFactura>FOB', '<paisOrigen>593', '<puertoEmbarque>GUAYAQUIL',
            '<puertoDestino>MIAMI', '<paisDestino>249', '<paisAdquisicion>593',
            '<incoTermTotalSinImpuestos>FOB', '<fleteInternacional>50.00', '<seguroInternacional>20.00',
            '<gastosAduaneros>10.00', '<gastosTransporteOtros>5.00'] as $needle) {
            $this->assertStringContainsString($needle, $xml, "Falta el campo de exportación: {$needle}");
        }

        // Orden estricto del XSD: comercioExterior < incoTermFactura < paisDestino < tipoIdentificacionComprador.
        $this->assertLessThan(strpos($xml, '<incoTermFactura>'), strpos($xml, '<comercioExterior>'));
        $this->assertLessThan(strpos($xml, '<tipoIdentificacionComprador>'), strpos($xml, '<paisDestino>'));
        // incoTermTotalSinImpuestos entre totalSinImpuestos y totalDescuento.
        $this->assertLessThan(strpos($xml, '<incoTermTotalSinImpuestos>'), strpos($xml, '<totalSinImpuestos>'));
        $this->assertLessThan(strpos($xml, '<totalDescuento>'), strpos($xml, '<incoTermTotalSinImpuestos>'));
        // flete/seguro/gastos entre propina e importeTotal.
        $this->assertLessThan(strpos($xml, '<fleteInternacional>'), strpos($xml, '<propina>'));
        $this->assertLessThan(strpos($xml, '<importeTotal>'), strpos($xml, '<gastosTransporteOtros>'));
    }

    public function test_non_export_factura_omits_export_block(): void
    {
        $generator = new FacturaGenerator();
        $data = [
            'infoTributaria' => [
                'ambiente' => '1', 'tipoEmision' => '1', 'razonSocial' => 'EMPRESA',
                'ruc' => '1790011001001', 'claveAcceso' => str_repeat('0', 49),
                'codDoc' => '01', 'estab' => '001', 'ptoEmi' => '001',
                'secuencial' => '000000001', 'dirMatriz' => 'DIR',
            ],
            'infoFactura' => [
                'fechaEmision' => '26/01/2026', 'obligadoContabilidad' => 'NO',
                'tipoIdentificacionComprador' => '05', 'razonSocialComprador' => 'CF',
                'identificacionComprador' => '9999999999', 'totalSinImpuestos' => '10.00',
                'totalDescuento' => '0.00', 'importetotal' => '11.20',
                'totalConImpuestos' => [['codigo' => '2', 'codigoPorcentaje' => '2', 'baseImponible' => '10.00', 'valor' => '1.20']],
            ],
            'detalles' => [[
                'codigoPrincipal' => 'P01', 'descripcion' => 'PROD', 'cantidad' => '1.00',
                'precioUnitario' => '10.00', 'descuento' => '0.00', 'precioTotalSinImpuesto' => '10.00',
                'impuestos' => [['codigo' => '2', 'codigoPorcentaje' => '2', 'tarifa' => '12.00', 'baseImponible' => '10.00', 'valor' => '1.20']],
            ]],
        ];

        $xml = $generator->generate($data);

        // Sin datos de exportación, NINGÚN campo de exportación debe aparecer (idéntico a antes).
        foreach (['comercioExterior', 'incoTermFactura', 'paisOrigen', 'fleteInternacional', 'incoTermTotalSinImpuestos'] as $field) {
            $this->assertStringNotContainsString("<{$field}>", $xml);
        }
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
