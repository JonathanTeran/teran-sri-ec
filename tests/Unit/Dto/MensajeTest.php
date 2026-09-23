<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Dto\Mensaje;

class MensajeTest extends TestCase
{
    /**
     * Regresión (2026-09-23): el SRI devolvió una factura con
     * «ARCHIVO NO CUMPLE ESTRUCTURA XML» y el motivo real venía en
     * informacionAdicional («No existe un contribuyente registrado con el RUC
     * 0992397535001»). La excepción solo llevaba el título, así que quien
     * emitía veía un error de estructura que no tenía nada que corregir en el
     * XML.
     */
    public function test_el_texto_incluye_la_informacion_adicional_del_sri(): void
    {
        $m = new Mensaje('35', 'ARCHIVO NO CUMPLE ESTRUCTURA XML', 'No existe un contribuyente registrado con el RUC 0992397535001', 'ERROR');

        $this->assertSame(
            'ARCHIVO NO CUMPLE ESTRUCTURA XML: No existe un contribuyente registrado con el RUC 0992397535001',
            $m->texto(),
        );
    }

    public function test_sin_informacion_adicional_el_texto_es_el_mensaje(): void
    {
        $this->assertSame('CLAVE ACCESO REGISTRADA', (new Mensaje('43', 'CLAVE ACCESO REGISTRADA', null, 'ERROR'))->texto());
        $this->assertSame('CLAVE ACCESO REGISTRADA', (new Mensaje('43', 'CLAVE ACCESO REGISTRADA', '   ', 'ERROR'))->texto());
    }

    public function test_no_repite_la_informacion_adicional_si_ya_esta_en_el_mensaje(): void
    {
        $m = new Mensaje('65', 'FECHA EMISIÓN EXTEMPORANEA', 'FECHA EMISIÓN EXTEMPORANEA', 'ERROR');

        $this->assertSame('FECHA EMISIÓN EXTEMPORANEA', $m->texto());
    }
}
