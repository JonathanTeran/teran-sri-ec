<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Exceptions\ValidationException;
use Teran\Sri\InfoAdicional;

/**
 * Reglas de <infoAdicional>: XSD offline (máx. 15 campos, 1..300 caracteres) y
 * Resolución NAC-DGERCGC26-00000027 / ficha técnica v2.34, Anexo 26
 * (campo «RUC Proveedor»).
 */
class InfoAdicionalTest extends TestCase
{
    private const RUC = '1792146739001';

    public function test_accepts_map_and_list_of_pairs_and_drops_empty_values(): void
    {
        $this->assertSame(
            ['Email' => 'a@b.com', 'Pedido' => '9842'],
            InfoAdicional::normalizar(['Email' => 'a@b.com', 'Vacío' => '', 'Nulo' => null, 'Pedido' => 9842]),
        );

        $this->assertSame(
            ['Email' => 'a@b.com', 'Vendedor' => 'Web'],
            InfoAdicional::normalizar([
                ['nombre' => 'Email', 'valor' => 'a@b.com'],
                ['name' => 'Vendedor', 'value' => ' Web '],
                ['nombre' => '', 'valor' => 'sin nombre'],
            ]),
        );

        $this->assertSame([], InfoAdicional::normalizar(null));
    }

    public function test_appends_provider_ruc_last_with_exact_field_name(): void
    {
        $campos = InfoAdicional::normalizar(['Email' => 'a@b.com'], self::RUC);

        $this->assertSame(['Email' => 'a@b.com', 'RUC Proveedor' => self::RUC], $campos);
        $this->assertSame('RUC Proveedor', array_key_last($campos));
    }

    public function test_replaces_an_existing_provider_field_instead_of_duplicating(): void
    {
        $campos = InfoAdicional::normalizar([' ruc proveedor ' => '0990000000001', 'Email' => 'a@b.com'], self::RUC);

        $this->assertSame(['Email' => 'a@b.com', 'RUC Proveedor' => self::RUC], $campos);
    }

    public function test_provider_ruc_accepts_separators_and_rejects_invalid_values(): void
    {
        $this->assertSame(self::RUC, InfoAdicional::validarRucProveedor('17921467-39 001'));

        foreach (['1792146739', '179214673900', '1792146739002', 'ABCDEFGHIJ001'] as $invalido) {
            try {
                InfoAdicional::validarRucProveedor($invalido);
                $this->fail("Debió rechazar '{$invalido}'.");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('NAC-DGERCGC26-00000027', $e->getMessage());
            }
        }
    }

    public function test_blank_provider_ruc_is_ignored(): void
    {
        $this->assertSame(['Email' => 'a@b.com'], InfoAdicional::normalizar(['Email' => 'a@b.com'], '  '));
    }

    public function test_more_than_fifteen_fields_is_rejected_counting_the_provider_field(): void
    {
        $quince = [];
        for ($i = 1; $i <= 15; $i++) {
            $quince["Campo {$i}"] = "Valor {$i}";
        }

        $this->assertCount(15, InfoAdicional::normalizar($quince));

        $this->expectException(ValidationException::class);
        InfoAdicional::normalizar($quince, self::RUC);
    }

    public function test_names_or_values_over_300_characters_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        InfoAdicional::normalizar(['Observaciones' => str_repeat('x', 301)]);
    }
}
