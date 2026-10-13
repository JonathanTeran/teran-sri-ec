<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Exceptions\ValidationException;
use Teran\Sri\Generators\FacturaGenerator;
use Teran\Sri\Schema\XsdValidator;

/**
 * Razón social, nombre comercial y direcciones de la factura admiten cualquier
 * carácter menos el salto de línea, como el XSD oficial del SRI (`[^\n]*`).
 *
 * El XSD del paquete declaraba `[^(\r?\n)]*`: dentro de una clase de caracteres
 * los paréntesis y el `?` son literales, así que rechazaba a clientes como
 * «HAID (ECUADOR) FEED CIA. LTDA.» (factura bloqueada el 2026-10-13) y
 * direcciones como «Av. Quito (frente al parque)», que el SRI sí acepta.
 */
class TextoLibreXsdTest extends TestCase
{
    private const XSD = __DIR__ . '/../../../resources/xsd/factura_v2.1.0.xsd';

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function textosValidos(): array
    {
        return [
            'comprador con paréntesis' => ['razonSocialComprador', 'HAID (ECUADOR) FEED CIA. LTDA.'],
            'emisor con paréntesis' => ['razonSocial', 'IMPORTADORA (QUITO) S.A.'],
            'nombre comercial con signo de pregunta' => ['nombreComercial', '¿QUÉ HAY? CAFÉ'],
            'dirección matriz con paréntesis' => ['dirMatriz', 'AV. QUITO 123 (FRENTE AL PARQUE)'],
            'dirección establecimiento con paréntesis' => ['dirEstablecimiento', 'KM 5 VÍA DAULE (BODEGA 2)'],
            'dirección comprador con paréntesis' => ['direccionComprador', 'CDLA. KENNEDY (MZ 4)'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function camposDeTexto(): array
    {
        return [
            'razonSocial' => ['razonSocial'],
            'nombreComercial' => ['nombreComercial'],
            'dirMatriz' => ['dirMatriz'],
            'dirEstablecimiento' => ['dirEstablecimiento'],
            'razonSocialComprador' => ['razonSocialComprador'],
            'direccionComprador' => ['direccionComprador'],
        ];
    }

    /**
     * @dataProvider textosValidos
     */
    public function test_accepts_parentheses_and_question_marks(string $campo, string $valor): void
    {
        $this->assertTrue(XsdValidator::validate($this->factura($campo, $valor), self::XSD));
    }

    /**
     * @dataProvider camposDeTexto
     */
    public function test_rejects_line_breaks(string $campo): void
    {
        try {
            XsdValidator::validate($this->factura($campo, "PRIMERA LÍNEA\nSEGUNDA LÍNEA"), self::XSD);
            $this->fail("El XSD aceptó un salto de línea en {$campo}.");
        } catch (ValidationException $e) {
            $errores = implode("\n", array_filter($e->getErrors(), 'is_string'));
            $this->assertStringContainsString("Element '{$campo}'", $errores);
        }
    }

    private function factura(string $campo, string $valor): string
    {
        $data = [
            'infoTributaria' => [
                'ambiente' => '1',
                'tipoEmision' => '1',
                'razonSocial' => 'EMPRESA PRUEBA',
                'nombreComercial' => 'EMPRESA PRUEBA',
                'ruc' => '1790011001001',
                'claveAcceso' => str_repeat('0', 49),
                'codDoc' => '01',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'DIRECCION MATRIZ',
            ],
            'infoFactura' => [
                'fechaEmision' => '13/10/2026',
                'dirEstablecimiento' => 'DIR ESTAB',
                'obligadoContabilidad' => 'NO',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CLIENTE',
                'identificacionComprador' => '1713175071',
                'direccionComprador' => 'DIR COMPRADOR',
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
        ];

        $bloque = isset($data['infoTributaria'][$campo]) ? 'infoTributaria' : 'infoFactura';
        $data[$bloque][$campo] = $valor;

        return (new FacturaGenerator())->generate($data);
    }
}
