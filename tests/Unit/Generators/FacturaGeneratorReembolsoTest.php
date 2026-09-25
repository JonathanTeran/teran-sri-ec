<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Generators\FacturaGenerator;
use Teran\Sri\Schema\XsdValidator;

/**
 * Factura de reembolso de gastos (intermediario, codDocReembolso 41): los
 * totales van en infoFactura y cada comprobante reembolsado en <reembolsos>,
 * en el orden estricto del XSD factura_v2.1.0 del SRI.
 */
class FacturaGeneratorReembolsoTest extends TestCase
{
    private const XSD = __DIR__ . '/../../../resources/xsd/factura_v2.1.0.xsd';

    /**
     * @return array{
     *     infoTributaria: array<string, string>,
     *     infoFactura: array<string, mixed>,
     *     detalles: list<array<string, mixed>>,
     *     reembolsos?: list<array<string, mixed>>
     * }
     */
    private function base(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'INTERMEDIARIA S.A.',
                'ruc' => '1790011001001',
                'claveAcceso' => '2509202601179001100100110010010000000011234567813',
                'codDoc' => '01',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'QUITO',
            ],
            'infoFactura' => [
                'fechaEmision' => '25/09/2026',
                'dirEstablecimiento' => 'QUITO',
                'obligadoContabilidad' => 'SI',
                'tipoIdentificacionComprador' => '04',
                'razonSocialComprador' => 'CLIENTE S.A.',
                'identificacionComprador' => '1790012345001',
                'totalSinImpuestos' => '100.00',
                'totalDescuento' => '0.00',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00'],
                ],
                'propina' => '0.00',
                'importetotal' => '115.00',
                'moneda' => 'DOLAR',
                'pagos' => [['formaPago' => '20', 'total' => '115.00']],
            ],
            'detalles' => [[
                'codigoPrincipal' => 'SERV',
                'descripcion' => 'HONORARIOS',
                'cantidad' => '1',
                'precioUnitario' => '100.00',
                'descuento' => '0.00',
                'precioTotalSinImpuesto' => '100.00',
                'impuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
                ],
            ]],
        ];
    }

    /**
     * @return array{
     *     infoTributaria: array<string, string>,
     *     infoFactura: array<string, mixed>,
     *     detalles: list<array<string, mixed>>,
     *     reembolsos?: list<array<string, mixed>>
     * }
     */
    private function conReembolsos(): array
    {
        $data = $this->base();
        $data['infoFactura']['codDocReembolso'] = '41';
        $data['infoFactura']['totalComprobantesReembolso'] = '57.50';
        $data['infoFactura']['totalBaseImponibleReembolso'] = '50.00';
        $data['infoFactura']['totalImpuestoReembolso'] = '7.50';
        $data['reembolsos'] = [[
            'tipoIdentificacionProveedorReembolso' => '04',
            'identificacionProveedorReembolso' => '0992397535001',
            'codPaisPagoProveedorReembolso' => '593',
            'tipoProveedorReembolso' => '02',
            'codDocReembolso' => '01',
            'estabDocReembolso' => '001',
            'ptoEmiDocReembolso' => '002',
            'secuencialDocReembolso' => '000000777',
            'fechaEmisionDocReembolso' => '10/09/2026',
            'numeroautorizacionDocReemb' => '1009202601099239753500120010020000007771234567818',
            'detalleImpuestos' => [
                ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => 15, 'baseImponibleReembolso' => 50, 'impuestoReembolso' => 7.5],
            ],
        ]];

        return $data;
    }

    public function test_factura_con_reembolsos_cumple_el_xsd_oficial(): void
    {
        $xml = (new FacturaGenerator())->generate($this->conReembolsos());

        $this->assertTrue(XsdValidator::validate($xml, self::XSD));
        $this->assertStringContainsString('<codDocReembolso>41</codDocReembolso>', $xml);
        $this->assertStringContainsString('<totalComprobantesReembolso>57.50</totalComprobantesReembolso>', $xml);
        $this->assertStringContainsString('<reembolsoDetalle>', $xml);
        $this->assertStringContainsString('<numeroautorizacionDocReemb>1009202601099239753500120010020000007771234567818</numeroautorizacionDocReemb>', $xml);
        $this->assertStringContainsString('<baseImponibleReembolso>50.00</baseImponibleReembolso>', $xml);
        $this->assertStringContainsString('<impuestoReembolso>7.50</impuestoReembolso>', $xml);
    }

    public function test_los_totales_de_reembolso_van_entre_total_descuento_y_total_con_impuestos(): void
    {
        $xml = (new FacturaGenerator())->generate($this->conReembolsos());

        $descuento = strpos($xml, '<totalDescuento>');
        $reembolso = strpos($xml, '<totalImpuestoReembolso>');
        $conImpuestos = strpos($xml, '<totalConImpuestos>');
        $this->assertNotFalse($descuento);
        $this->assertNotFalse($reembolso);
        $this->assertNotFalse($conImpuestos);
        $this->assertTrue($descuento < $reembolso && $reembolso < $conImpuestos, 'Orden del XSD roto');

        // <reembolsos> va después de <detalles> y antes de <infoAdicional>/firma.
        $this->assertTrue(strpos($xml, '</detalles>') < strpos($xml, '<reembolsos>'));
    }

    public function test_sin_reembolsos_la_factura_no_cambia(): void
    {
        $xml = (new FacturaGenerator())->generate($this->base());

        $this->assertTrue(XsdValidator::validate($xml, self::XSD));
        $this->assertStringNotContainsString('Reembolso', $xml);
        $this->assertStringNotContainsString('<reembolsos>', $xml);
    }
}
