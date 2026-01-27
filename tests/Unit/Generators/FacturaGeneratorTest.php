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

        $this->assertStringContainsString('<factura id="comprobante" version="2.1.0">', $xml);
        $this->assertStringContainsString('<razonSocial>EMPRESA PRUEBA</razonSocial>', $xml);
        $this->assertStringContainsString('<importetotal>11.20</importetotal>', $xml);
    }
}
