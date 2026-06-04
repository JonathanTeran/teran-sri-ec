<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Emission;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Emission\EmissionResult;
use Teran\Sri\Emission\EmissionStatus;

class EmissionResultTest extends TestCase
{
    public function test_property_and_array_access_both_work(): void
    {
        $r = new EmissionResult(
            status: EmissionStatus::Authorized,
            claveAcceso: '2601...819',
            signedXml: '<factura/>',
            numeroAutorizacion: '1234567890',
        );

        // Acceso por propiedad (estilo 2.0)
        $this->assertSame(EmissionStatus::Authorized, $r->status);
        $this->assertSame('2601...819', $r->claveAcceso);

        // Acceso por array (compat 1.x)
        $this->assertSame('2601...819', $r['claveAcceso']);
        $this->assertSame('<factura/>', $r['xmlFirmado']);
        $this->assertSame('1234567890', $r['numeroAutorizacion']);
        $this->assertTrue(isset($r['claveAcceso']));
        $this->assertFalse(isset($r['inexistente']));
    }

    public function test_is_immutable_via_array_access(): void
    {
        $r = new EmissionResult(EmissionStatus::Error, 'x', '<xml/>');
        $this->expectException(\LogicException::class);
        $r['claveAcceso'] = 'otro';
    }
}
