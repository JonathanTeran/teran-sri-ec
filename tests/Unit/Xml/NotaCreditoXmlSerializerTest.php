<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\NotaCredito;
use Teran\Sri\Generators\NotaCreditoGenerator;
use Teran\Sri\Tests\Support\XmlParity;
use Teran\Sri\Xml\NotaCreditoXmlSerializer;

class NotaCreditoXmlSerializerTest extends TestCase
{
    private const CLAVE = '1002202601179001100100410010010000000041234567814';

    private function parityData(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'EMPRESA DE PRUEBA S.A.',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000004',
                'dirMatriz' => 'Av. Amazonas N1-01, Quito',
            ],
            'infoNotaCredito' => [
                'fechaEmision' => '10/02/2026',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CLIENTE FINAL',
                'identificacionComprador' => '9999999999',
                'obligadoContabilidad' => 'NO',
                'codDocModificado' => '01',
                'numDocModificado' => '001-001-000000100',
                'fechaEmisionDocSustento' => '01/02/2026',
                'totalSinImpuestos' => '50.00',
                'valorModificacion' => '57.50',
                'moneda' => 'DOLAR',
                'totalConImpuestos' => [
                    [
                        'codigo' => '2',
                        'codigoPorcentaje' => '4',
                        'baseImponible' => '50.00',
                        'valor' => '7.50',
                    ],
                ],
                'motivo' => 'Devolucion de mercaderia',
            ],
            'detalles' => [
                [
                    'codigoInterno' => 'PROD001',   // 1.x generator reads this
                    'codigoPrincipal' => 'PROD001',  // new DTO reads this
                    'descripcion' => 'Producto de prueba',
                    'cantidad' => '1.000000',
                    'precioUnitario' => '50.000000',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '50.00',
                    'impuestos' => [
                        [
                            'codigo' => '2',
                            'codigoPorcentaje' => '4',
                            'baseImponible' => '50.00',
                            'valor' => '7.50',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_matches_1x_generator(): void
    {
        $data = $this->parityData();

        // Prepare 1.x generator data: inject claveAcceso + codDoc as SRI.php would
        $data1x = $data;
        $data1x['infoTributaria']['claveAcceso'] = self::CLAVE;
        $data1x['infoTributaria']['codDoc'] = '04';

        $expected = (new NotaCreditoGenerator())->generate($data1x);
        $actual = (new NotaCreditoXmlSerializer())->serialize(NotaCredito::fromArray($data), self::CLAVE);

        XmlParity::assertSameStructure($expected, $actual, $this);
    }

    public function test_escapes_special_chars(): void
    {
        $data = $this->parityData();
        $data['infoTributaria']['razonSocial'] = 'EMPRESA J & M <TEST>';
        $data['infoNotaCredito']['razonSocialComprador'] = 'CLIENTE <A & B>';
        $data['infoNotaCredito']['motivo'] = 'Devolucion & ajuste < precio';

        $xml = (new NotaCreditoXmlSerializer())->serialize(NotaCredito::fromArray($data), self::CLAVE);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'El XML debe ser bien formado');
        $this->assertSame(
            'EMPRESA J & M <TEST>',
            $dom->getElementsByTagName('razonSocial')->item(0)->textContent
        );
        $this->assertSame(
            'CLIENTE <A & B>',
            $dom->getElementsByTagName('razonSocialComprador')->item(0)->textContent
        );
    }

    public function test_is_deterministic(): void
    {
        $nc = NotaCredito::fromArray($this->parityData());
        $s = new NotaCreditoXmlSerializer();
        $this->assertSame(
            $s->serialize($nc, self::CLAVE),
            $s->serialize($nc, self::CLAVE)
        );
    }
}
