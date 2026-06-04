<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Impuesto;
use Teran\Sri\Money\Money;
use Teran\Sri\Exceptions\ValidationException;

class ImpuestoTest extends TestCase
{
    public function test_from_array_maps_1x_keys(): void
    {
        $imp = Impuesto::fromArray([
            'codigo' => '2',
            'codigoPorcentaje' => '4',
            'tarifa' => '15.00',
            'baseImponible' => '100.00',
            'valor' => '15.00',
        ]);

        $this->assertSame('2', $imp->codigo);
        $this->assertSame('4', $imp->codigoPorcentaje);
        $this->assertSame('100.00', $imp->baseImponible->format(2));
        $this->assertSame('15.00', $imp->valor->format(2));
    }

    public function test_rejects_missing_codigo(): void
    {
        $this->expectException(ValidationException::class);
        Impuesto::fromArray(['baseImponible' => '100.00', 'valor' => '15.00']);
    }
}
