<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\Factura;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Pago;
use Teran\Sri\Exceptions\ValidationException;

class FacturaTest extends TestCase
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
            'infoFactura' => [
                'fechaEmision' => '26/01/2026',
                'tipoIdentificacionComprador' => '05',
                'razonSocialComprador' => 'CLIENTE FINAL',
                'identificacionComprador' => '9999999999',
                'totalSinImpuestos' => '100.00',
                'totalDescuento' => '0.00',
                'importeTotal' => '115.00',
                'totalConImpuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00'],
                ],
                'pagos' => [
                    ['formaPago' => '01', 'total' => '115.00'],
                ],
            ],
            'detalles' => [
                [
                    'codigoPrincipal' => 'PROD001',
                    'descripcion' => 'Producto de prueba',
                    'cantidad' => '1.00',
                    'precioUnitario' => '100.00',
                    'descuento' => '0.00',
                    'precioTotalSinImpuesto' => '100.00',
                    'impuestos' => [
                        ['codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00'],
                    ],
                ],
            ],
        ];
    }

    public function test_from_array_builds_full_aggregate(): void
    {
        $factura = Factura::fromArray($this->validData());

        $this->assertSame('1790011001001', $factura->infoTributaria->ruc);
        $this->assertSame('26/01/2026', $factura->fechaEmision);
        $this->assertCount(1, $factura->detalles);
        $this->assertInstanceOf(Detalle::class, $factura->detalles[0]);
        $this->assertCount(1, $factura->pagos);
        $this->assertInstanceOf(Pago::class, $factura->pagos[0]);
        $this->assertSame('115.00', $factura->importeTotal->format(2));
    }

    public function test_rejects_factura_without_detalles(): void
    {
        $data = $this->validData();
        $data['detalles'] = [];

        $this->expectException(ValidationException::class);
        Factura::fromArray($data);
    }

    // Item 6: empty pagos must throw
    public function test_rejects_factura_without_pagos(): void
    {
        $data = $this->validData();
        $data['infoFactura']['pagos'] = [];

        $this->expectException(ValidationException::class);
        Factura::fromArray($data);
    }

    // Item 7: invalid obligadoContabilidad must throw
    public function test_rejects_invalid_obligado_contabilidad(): void
    {
        $data = $this->validData();
        $data['infoFactura']['obligadoContabilidad'] = 'MAYBE';

        $this->expectException(ValidationException::class);
        Factura::fromArray($data);
    }
}
