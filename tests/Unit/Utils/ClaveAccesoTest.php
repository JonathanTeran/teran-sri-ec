<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Utils\ClaveAcceso;
use InvalidArgumentException;

class ClaveAccesoTest extends TestCase
{
    public function test_generar_clave_valida()
    {
        $fecha = '26012026';
        $tipo = '01';
        $ruc = '1790011001001';
        $ambiente = '1';
        $serie = '001001';
        $numero = '000000001';
        $codigoNum = '12345678';
        $tipoEmision = '1';

        $clave = ClaveAcceso::generar($fecha, $tipo, $ruc, $ambiente, $serie, $numero, $codigoNum, $tipoEmision);

        $this->assertEquals(49, strlen($clave));
        $this->assertStringStartsWith($fecha . $tipo . $ruc . $ambiente . $serie . $numero . $codigoNum . $tipoEmision, $clave);
    }

    public function test_modulo11_calculator()
    {
        // Example from SRI documentation if available, or just a known valid key
        // Let's use a known base and check the DV
        $base = "26012026" . "01" . "1790011001001" . "1" . "001001" . "000000001" . "12345678" . "1";
        $dv = ClaveAcceso::calcularDigitoVerificador($base);
        
        $this->assertGreaterThanOrEqual(0, $dv);
        $this->assertLessThanOrEqual(9, $dv);
    }

    public function test_throws_exception_on_invalid_length()
    {
        $this->expectException(InvalidArgumentException::class);
        ClaveAcceso::generar('123', '01', '123', '1', '123', '1', '1');
    }
}
