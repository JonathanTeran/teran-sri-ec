<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Pago;
use Teran\Sri\Catalogs2\FormaPago;
use Teran\Sri\Exceptions\ValidationException;

class PagoTest extends TestCase
{
    public function test_from_array_resolves_forma_pago_enum(): void
    {
        $pago = Pago::fromArray(['formaPago' => '01', 'total' => '112.00']);

        $this->assertSame(FormaPago::SinUtilizacionSistemaFinanciero, $pago->formaPago);
        $this->assertSame('112.00', $pago->total->format(2));
    }

    public function test_rejects_unknown_forma_pago(): void
    {
        $this->expectException(ValidationException::class);
        Pago::fromArray(['formaPago' => '99', 'total' => '1.00']);
    }
}
