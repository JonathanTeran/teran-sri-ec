<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\GuiaRemision;
use Teran\Sri\Generators\GuiaRemisionGenerator;
use Teran\Sri\Tests\Support\XmlParity;
use Teran\Sri\Xml\GuiaRemisionXmlSerializer;

class GuiaRemisionXmlSerializerTest extends TestCase
{
    private const CLAVE = '1002202601179001100100610010010000000061234567816';

    private function parityData(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'EMPRESA TRANSPORTISTA S.A.',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000006',
                'dirMatriz' => 'Av. Amazonas N1-01, Quito',
            ],
            'infoGuiaRemision' => [
                'dirEstablecimiento' => 'Calle Falsa 123',
                'dirPartida' => 'Bodega Principal, Quito',
                'razonSocialTransportista' => 'TRANSPORTES EC S.A.',
                'tipoIdentificacionTransportista' => '04',
                'rucTransportista' => '1790011002001',
                'obligadoContabilidad' => 'SI',
                'fechaIniTransporte' => '10/02/2026',
                'fechaFinTransporte' => '11/02/2026',
                'placa' => 'ABC-1234',
            ],
            'destinatarios' => [
                [
                    'identificacionDestinatario' => '0912345678001',
                    'razonSocialDestinatario' => 'CLIENTE DESTINO S.A.',
                    'dirDestinatario' => 'Av. 9 de Octubre, Guayaquil',
                    'motivoTraslado' => 'Venta',
                    'codEstabDestino' => '001',
                    'ruta' => 'Quito-Guayaquil',
                    'codDocSustento' => '01',
                    'numDocSustento' => '001-001-000000100',
                    'numAutDocSustento' => '0123456789',
                    'fechaEmisionDocSustento' => '10/02/2026',
                    'detalles' => [
                        [
                            'codigoInterno' => 'PROD001',
                            'descripcion' => 'Producto de prueba',
                            'cantidad' => '10.000000',
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
        $data1x['infoTributaria']['codDoc'] = '06';

        $expected = (new GuiaRemisionGenerator())->generate($data1x);
        $actual = (new GuiaRemisionXmlSerializer())->serialize(GuiaRemision::fromArray($data), self::CLAVE);

        XmlParity::assertSameStructure($expected, $actual, $this);
    }

    public function test_escapes_special_chars(): void
    {
        $data = $this->parityData();
        $data['infoTributaria']['razonSocial'] = 'EMPRESA J & M <TEST>';
        $data['infoGuiaRemision']['razonSocialTransportista'] = 'TRANSPORTE <A & B>';
        $data['destinatarios'][0]['motivoTraslado'] = 'Traslado & ajuste < precio';

        $xml = (new GuiaRemisionXmlSerializer())->serialize(GuiaRemision::fromArray($data), self::CLAVE);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'El XML debe ser bien formado');
        $this->assertSame(
            'EMPRESA J & M <TEST>',
            $dom->getElementsByTagName('razonSocial')->item(0)->textContent
        );
        $this->assertSame(
            'TRANSPORTE <A & B>',
            $dom->getElementsByTagName('razonSocialTransportista')->item(0)->textContent
        );
        $this->assertSame(
            'Traslado & ajuste < precio',
            $dom->getElementsByTagName('motivoTraslado')->item(0)->textContent
        );
    }

    public function test_is_deterministic(): void
    {
        $gr = GuiaRemision::fromArray($this->parityData());
        $s = new GuiaRemisionXmlSerializer();
        $this->assertSame(
            $s->serialize($gr, self::CLAVE),
            $s->serialize($gr, self::CLAVE)
        );
    }
}
