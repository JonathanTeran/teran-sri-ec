<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\LiquidacionCompra;
use Teran\Sri\Documents\Detalle;
use Teran\Sri\Documents\Pago;
use Teran\Sri\Exceptions\ValidationException;

class LiquidacionCompraTest extends TestCase
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
            'infoLiquidacionCompra' => [
                'fechaEmision' => '26/01/2026',
                'tipoIdentificacionProveedor' => '05',
                'razonSocialProveedor' => 'PROVEEDOR NATURAL',
                'identificacionProveedor' => '1712345678',
                'direccionProveedor' => 'Av. Amazonas N23-45',
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
                    'codigoPrincipal' => 'SERV001',
                    'descripcion' => 'Servicio de prueba',
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
        $liq = LiquidacionCompra::fromArray($this->validData());

        $this->assertSame('1790011001001', $liq->infoTributaria->ruc);
        $this->assertSame('26/01/2026', $liq->fechaEmision);
        $this->assertSame('05', $liq->tipoIdentificacionProveedor);
        $this->assertSame('PROVEEDOR NATURAL', $liq->razonSocialProveedor);
        $this->assertSame('1712345678', $liq->identificacionProveedor);
        $this->assertSame('Av. Amazonas N23-45', $liq->direccionProveedor);
        $this->assertCount(1, $liq->detalles);
        $this->assertInstanceOf(Detalle::class, $liq->detalles[0]);
        $this->assertCount(1, $liq->pagos);
        $this->assertInstanceOf(Pago::class, $liq->pagos[0]);
        $this->assertSame('115.00', $liq->importeTotal->format(2));
    }

    public function test_rejects_liquidacion_without_detalles(): void
    {
        $data = $this->validData();
        $data['detalles'] = [];

        $this->expectException(ValidationException::class);
        LiquidacionCompra::fromArray($data);
    }

    public function test_rejects_liquidacion_without_pagos(): void
    {
        $data = $this->validData();
        $data['infoLiquidacionCompra']['pagos'] = [];

        $this->expectException(ValidationException::class);
        LiquidacionCompra::fromArray($data);
    }

    public function test_rejects_invalid_obligado_contabilidad(): void
    {
        $data = $this->validData();
        $data['infoLiquidacionCompra']['obligadoContabilidad'] = 'MAYBE';

        $this->expectException(ValidationException::class);
        LiquidacionCompra::fromArray($data);
    }

    public function test_rejects_invalid_tipo_identificacion_proveedor(): void
    {
        // El XSD solo admite 04-08 para el proveedor ('09', consumidor final, no aplica).
        $data = $this->validData();
        $data['infoLiquidacionCompra']['tipoIdentificacionProveedor'] = '09';

        $this->expectException(ValidationException::class);
        LiquidacionCompra::fromArray($data);
    }

    public function test_rejects_missing_razon_social_proveedor(): void
    {
        $data = $this->validData();
        unset($data['infoLiquidacionCompra']['razonSocialProveedor']);

        $this->expectException(ValidationException::class);
        LiquidacionCompra::fromArray($data);
    }
}
