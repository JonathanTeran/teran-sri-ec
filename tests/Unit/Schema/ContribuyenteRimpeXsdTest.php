<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Teran\Sri\Exceptions\ValidationException;
use Teran\Sri\Generators\FacturaGenerator;
use Teran\Sri\Generators\LiquidacionCompraGenerator;
use Teran\Sri\Schema\XsdValidator;
use Teran\Sri\Tests\Unit\Generators\LiquidacionCompraGeneratorTest;

/**
 * `<contribuyenteRimpe>` admite solo las dos leyendas del XSD oficial del SRI.
 *
 * El XSD de factura del paquete la declaraba `maxLength 40`: rechazaba la
 * leyenda de negocio popular (45 caracteres) y dejaba pasar leyendas
 * inventadas que el SRI devuelve con «ARCHIVO NO CUMPLE ESTRUCTURA XML».
 */
class ContribuyenteRimpeXsdTest extends TestCase
{
    private const XSD_DIR = __DIR__ . '/../../../resources/xsd/';

    /**
     * @return array<string, array{0: string}>
     */
    public static function leyendasOficiales(): array
    {
        return [
            'rimpe emprendedor' => ['CONTRIBUYENTE RÉGIMEN RIMPE'],
            'rimpe negocio popular' => ['CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function leyendasInventadas(): array
    {
        return [
            // Rechazada por el SRI el 2026-09-18 (cabe en 40 caracteres).
            'sufijo emprendedor' => ['CONTRIBUYENTE RÉGIMEN RIMPE EMPRENDEDOR'],
            'guion largo' => ['CONTRIBUYENTE NEGOCIO POPULAR — RÉGIMEN RIMPE'],
            'sin tilde' => ['CONTRIBUYENTE REGIMEN RIMPE'],
            'minúsculas' => ['Contribuyente Régimen RIMPE'],
            'espacio al final' => ['CONTRIBUYENTE RÉGIMEN RIMPE '],
        ];
    }

    /**
     * @dataProvider leyendasOficiales
     */
    public function test_factura_accepts_official_legend(string $leyenda): void
    {
        $this->assertTrue(XsdValidator::validate($this->factura($leyenda), self::XSD_DIR . 'factura_v2.1.0.xsd'));
    }

    /**
     * @dataProvider leyendasInventadas
     */
    public function test_factura_rejects_invented_legend(string $leyenda): void
    {
        $this->assertRejected($this->factura($leyenda), 'factura_v2.1.0.xsd');
    }

    /**
     * @dataProvider leyendasOficiales
     */
    public function test_liquidacion_accepts_official_legend(string $leyenda): void
    {
        $this->assertTrue(XsdValidator::validate($this->liquidacion($leyenda), self::XSD_DIR . 'liquidacionCompra_v1.1.0.xsd'));
    }

    /**
     * @dataProvider leyendasInventadas
     */
    public function test_liquidacion_rejects_invented_legend(string $leyenda): void
    {
        $this->assertRejected($this->liquidacion($leyenda), 'liquidacionCompra_v1.1.0.xsd');
    }

    private function assertRejected(string $xml, string $xsd): void
    {
        try {
            XsdValidator::validate($xml, self::XSD_DIR . $xsd);
            $this->fail('El XSD aceptó una leyenda RIMPE que no es oficial.');
        } catch (ValidationException $e) {
            $errores = implode("\n", array_filter($e->getErrors(), 'is_string'));
            $this->assertStringContainsString("Element 'contribuyenteRimpe'", $errores);
        }
    }

    private function factura(string $leyenda): string
    {
        return (new FacturaGenerator())->generate([
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'EMPRESA PRUEBA',
                'ruc' => '1790011001001',
                'claveAcceso' => str_repeat('0', 49),
                'codDoc' => '01',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'DIRECCION MATRIZ',
                'contribuyenteRimpe' => $leyenda,
            ],
            'infoFactura' => [
                'fechaEmision' => '23/09/2026',
                'dirEstablecimiento' => 'DIR ESTAB',
                'obligadoContabilidad' => 'NO',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CLIENTE',
                'identificacionComprador' => '1713175071',
                'totalSinImpuestos' => '10.00',
                'totalDescuento' => '0.00',
                'importetotal' => '11.50',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '10.00', 'valor' => '1.50'],
                ],
                'pagos' => [['formaPago' => '01', 'total' => '11.50']],
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
                        ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '10.00', 'valor' => '1.50'],
                    ],
                ],
            ],
        ]);
    }

    private function liquidacion(string $leyenda): string
    {
        /** @var array<string, array<string, mixed>> $data */
        $data = (new ReflectionMethod(LiquidacionCompraGeneratorTest::class, 'validData'))
            ->invoke(new LiquidacionCompraGeneratorTest('fixture'));
        $data['infoTributaria']['contribuyenteRimpe'] = $leyenda;

        return (new LiquidacionCompraGenerator())->generate($data);
    }
}
