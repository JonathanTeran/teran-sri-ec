<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Catalogs2;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Catalogs2\Ambiente;
use Teran\Sri\Catalogs2\TipoComprobante;
use Teran\Sri\Catalogs2\FormaPago;

class EnumsTest extends TestCase
{
    public function test_ambiente_has_sri_codes(): void
    {
        $this->assertSame('1', Ambiente::Pruebas->value);
        $this->assertSame('2', Ambiente::Produccion->value);
    }

    public function test_tipo_comprobante_factura_code(): void
    {
        $this->assertSame('01', TipoComprobante::Factura->value);
        $this->assertSame(TipoComprobante::Factura, TipoComprobante::from('01'));
    }

    public function test_forma_pago_known_code_resolves(): void
    {
        $this->assertSame(FormaPago::SinUtilizacionSistemaFinanciero, FormaPago::from('01'));
        $this->assertNull(FormaPago::tryFrom('zzz'));
    }
}
