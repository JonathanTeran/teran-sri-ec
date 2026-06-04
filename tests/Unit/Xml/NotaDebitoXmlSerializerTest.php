<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\NotaDebito;
use Teran\Sri\Generators\NotaDebitoGenerator;
use Teran\Sri\Tests\Support\XmlParity;
use Teran\Sri\Xml\NotaDebitoXmlSerializer;

class NotaDebitoXmlSerializerTest extends TestCase
{
    private const CLAVE = '1503202601179001100100510010010000000051234567812';

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
                'secuencial' => '000000005',
                'dirMatriz' => 'Av. Amazonas N1-01, Quito',
            ],
            'infoNotaDebito' => [
                'fechaEmision' => '15/03/2026',
                'dirEstablecimiento' => 'Calle Principal 100 y Secundaria',
                'tipoIdentificacionComprador' => '04',
                'razonSocialComprador' => 'EMPRESA ABC S.A.',
                'identificacionComprador' => '1790011001001',
                'contribuyenteEspecial' => '9999',
                'obligadoContabilidad' => 'SI',
                'rise' => 'Contribuyente Régimen Simplificado RISE',
                'codDocModificado' => '01',
                'numDocModificado' => '001-001-000000200',
                'fechaEmisionDocSustento' => '01/03/2026',
                'totalSinImpuestos' => '100.00',
                'impuestos' => [
                    [
                        'codigo' => '2',
                        'codigoPorcentaje' => '4',
                        'baseImponible' => '100.00',
                        'valor' => '15.00',
                    ],
                ],
                'valorTotal' => '115.00',
                'pagos' => [
                    ['formaPago' => '01', 'total' => '115.00'],
                ],
            ],
            'motivos' => [
                ['razon' => 'Intereses por mora', 'valor' => '15.00'],
            ],
        ];
    }

    public function test_matches_1x_generator(): void
    {
        $data = $this->parityData();

        // Prepare 1.x generator data: inject claveAcceso + codDoc as SRI.php would
        $data1x = $data;
        $data1x['infoTributaria']['claveAcceso'] = self::CLAVE;
        $data1x['infoTributaria']['codDoc'] = '05';

        $expected = (new NotaDebitoGenerator())->generate($data1x);
        $actual = (new NotaDebitoXmlSerializer())->serialize(NotaDebito::fromArray($data), self::CLAVE);

        XmlParity::assertSameStructure($expected, $actual, $this);
    }

    public function test_escapes_special_chars(): void
    {
        $data = $this->parityData();
        $data['infoTributaria']['razonSocial'] = 'EMPRESA J & M <TEST>';
        $data['infoNotaDebito']['razonSocialComprador'] = 'CLIENTE <A & B>';
        $data['motivos'][0]['razon'] = 'Intereses & recargos < monto';

        $xml = (new NotaDebitoXmlSerializer())->serialize(NotaDebito::fromArray($data), self::CLAVE);

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
        $this->assertSame(
            'Intereses & recargos < monto',
            $dom->getElementsByTagName('razon')->item(0)->textContent
        );
    }

    public function test_is_deterministic(): void
    {
        $nd = NotaDebito::fromArray($this->parityData());
        $s = new NotaDebitoXmlSerializer();
        $this->assertSame(
            $s->serialize($nd, self::CLAVE),
            $s->serialize($nd, self::CLAVE)
        );
    }
}
