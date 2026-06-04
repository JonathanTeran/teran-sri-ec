<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Retencion;
use Teran\Sri\Generators\RetencionGenerator;
use Teran\Sri\Tests\Support\XmlParity;
use Teran\Sri\Xml\RetencionXmlSerializer;

class RetencionXmlSerializerTest extends TestCase
{
    private const CLAVE = '1002202601179001100100710010010000000071234567817';

    private function parityData(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'AGENTE RETENCION S.A.',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000007',
                'dirMatriz' => 'Av. Amazonas N1-01, Quito',
            ],
            'infoCompRetencion' => [
                'fechaEmision' => '10/02/2026',
                'dirEstablecimiento' => 'Calle Falsa 123',
                'obligadoContabilidad' => 'SI',
                'tipoIdentificacionSujetoRetenido' => '04',
                'razonSocialSujetoRetenido' => 'PROVEEDOR EC S.A.',
                'identificacionSujetoRetenido' => '1790011002001',
                'periodoFiscal' => '02/2026',
            ],
            'docsSustento' => [
                [
                    'codSustento' => '01',
                    'codDocSustento' => '01',
                    'numDocSustento' => '001-001-000000100',
                    'fechaEmisionDocSustento' => '05/02/2026',
                    'totalSinImpuestos' => '1000.00',
                    'importeTotal' => '1120.00',
                    'impuestosDocSustento' => [
                        [
                            'codImpuestoDocSustento' => '2',
                            'codigoPorcentaje' => '4',
                            'baseImponible' => '1000.00',
                            'tarifa' => '12.00',
                            'factorProporcionalidad' => '1.00',
                            'baseImponibleModificada' => '1000.00',
                            'valorImpuesto' => '120.00',
                        ],
                    ],
                    'retenciones' => [
                        [
                            'codigo' => '2',
                            'codigoRetencion' => '10',
                            'baseImponible' => '1000.00',
                            'porcentajeRetener' => '10',
                            'valorRetenido' => '100.00',
                        ],
                    ],
                    'pagos' => [
                        [
                            'formaPago' => '01',
                            'total' => '1020.00',
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
        $data1x['infoTributaria']['codDoc'] = '07';

        $expected = (new RetencionGenerator())->generate($data1x);
        $actual = (new RetencionXmlSerializer())->serialize(Retencion::fromArray($data), self::CLAVE);

        XmlParity::assertSameStructure($expected, $actual, $this);
    }

    public function test_escapes_special_chars(): void
    {
        $data = $this->parityData();
        $data['infoTributaria']['razonSocial'] = 'EMPRESA J & M <TEST>';
        $data['infoCompRetencion']['razonSocialSujetoRetenido'] = 'PROVEEDOR <A & B>';

        $xml = (new RetencionXmlSerializer())->serialize(Retencion::fromArray($data), self::CLAVE);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'El XML debe ser bien formado');
        $this->assertSame(
            'EMPRESA J & M <TEST>',
            $dom->getElementsByTagName('razonSocial')->item(0)->textContent
        );
        $this->assertSame(
            'PROVEEDOR <A & B>',
            $dom->getElementsByTagName('razonSocialSujetoRetenido')->item(0)->textContent
        );
    }

    public function test_is_deterministic(): void
    {
        $ret = Retencion::fromArray($this->parityData());
        $s = new RetencionXmlSerializer();
        $this->assertSame(
            $s->serialize($ret, self::CLAVE),
            $s->serialize($ret, self::CLAVE)
        );
    }
}
