<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\NotaCredito;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Exceptions\ValidationException;

class NotaCreditoTest extends TestCase
{
    private function validData(): array
    {
        return [
            'infoTributaria' => [
                'ambiente' => '1',
                'razonSocial' => 'MI EMPRESA S.A.',
                'ruc' => '1790011001001',
                'estab' => '001',
                'ptoEmi' => '001',
                'secuencial' => '000000001',
                'dirMatriz' => 'Quito, Ecuador',
            ],
            'infoNotaCredito' => [
                'fechaEmision' => '10/02/2026',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CLIENTE FINAL',
                'identificacionComprador' => '9999999999',
                'obligadoContabilidad' => 'NO',
                'codDocModificado' => '01',
                'numDocModificado' => '001-001-000000100',
                'fechaEmisionDocSustento' => '01/02/2026',
                'totalSinImpuestos' => '50.00',
                'valorModificacion' => '57.50',
                'moneda' => 'DOLAR',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '50.00', 'valor' => '7.50'],
                ],
                'motivo' => 'Devolución parcial de mercadería',
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'PROD001',
                    'descripcion' => 'Producto devuelto',
                    'cantidad' => '1.000000',
                    'precioUnitario' => '50.000000',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '50.00',
                    'impuestos' => [
                        ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '50.00', 'valor' => '7.50'],
                    ],
                ],
            ],
        ];
    }

    public function test_from_array_builds_aggregate(): void
    {
        $nc = NotaCredito::fromArray($this->validData());

        $this->assertSame('1790011001001', $nc->infoTributaria->ruc);
        $this->assertSame('10/02/2026', $nc->fechaEmision);
        $this->assertSame('01', $nc->codDocModificado);
        $this->assertSame('57.50', $nc->valorModificacion->format(2));
        $this->assertCount(1, $nc->detalles);
        $this->assertInstanceOf(Detalle::class, $nc->detalles[0]);
        $this->assertCount(1, $nc->totalConImpuestos);
        $this->assertSame('DOLAR', $nc->moneda);
        $this->assertSame('Devolución parcial de mercadería', $nc->motivo);
    }

    public function test_rejects_without_detalles(): void
    {
        $data = $this->validData();
        $data['detalles'] = [];

        $this->expectException(ValidationException::class);
        NotaCredito::fromArray($data);
    }

    public function test_rejects_invalid_fecha_emision(): void
    {
        $data = $this->validData();
        $data['infoNotaCredito']['fechaEmision'] = '2026-02-10';

        $this->expectException(ValidationException::class);
        NotaCredito::fromArray($data);
    }
}
