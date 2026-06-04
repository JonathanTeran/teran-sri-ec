<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Exceptions\ValidationException;

class DetalleTest extends TestCase
{
    public function test_from_array_builds_nested_impuestos(): void
    {
        $det = Detalle::fromArray([
            'codigoPrincipal' => 'PROD001',
            'descripcion' => 'Producto de prueba',
            'cantidad' => '1.00',
            'precioUnitario' => '100.00',
            'descuento' => '0.00',
            'precioTotalSinImpuesto' => '100.00',
            'impuestos' => [
                ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
            ],
        ]);

        $this->assertSame('PROD001', $det->codigoPrincipal);
        $this->assertCount(1, $det->impuestos);
        $this->assertInstanceOf(Impuesto::class, $det->impuestos[0]);
        $this->assertSame('1.000000', $det->cantidad->format(6));
    }

    public function test_rejects_detalle_without_impuestos(): void
    {
        $this->expectException(ValidationException::class);
        Detalle::fromArray([
            'codigoPrincipal' => 'P1',
            'descripcion' => 'x',
            'cantidad' => '1',
            'precioUnitario' => '1',
            'precioTotalSinImpuesto' => '1',
            'impuestos' => [],
        ]);
    }

    // Item 8: empty codigoPrincipal must throw
    public function test_rejects_empty_codigo_principal(): void
    {
        $this->expectException(ValidationException::class);
        Detalle::fromArray([
            'codigoPrincipal' => '',
            'descripcion' => 'Producto',
            'cantidad' => '1',
            'precioUnitario' => '1',
            'descuento' => '0',
            'precioTotalSinImpuesto' => '1',
            'impuestos' => [
                ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '1.00', 'valor' => '0.15'],
            ],
        ]);
    }
}
