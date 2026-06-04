<?php

declare(strict_types=1);

namespace Teran\Sri\Tests\Unit\Documents;

use PHPUnit\Framework\TestCase;
use Teran\Sri\Documents\NotaDebito;
use Teran\Sri\Documents\Motivo;
use Teran\Sri\Exceptions\ValidationException;

class NotaDebitoTest extends TestCase
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
                'secuencial' => '000000005',
                'dirMatriz' => 'Quito, Ecuador',
            ],
            'infoNotaDebito' => [
                'fechaEmision' => '15/03/2026',
                'tipoIdentificacionComprador' => '04',
                'razonSocialComprador' => 'EMPRESA ABC S.A.',
                'identificacionComprador' => '1790011001001',
                'obligadoContabilidad' => 'SI',
                'codDocModificado' => '01',
                'numDocModificado' => '001-001-000000200',
                'fechaEmisionDocSustento' => '01/03/2026',
                'totalSinImpuestos' => '100.00',
                'impuestos' => [
                    ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'valor' => '15.00'],
                ],
                'valorTotal' => '115.00',
                'pagos' => [
                    ['formaPago' => '01', 'total' => '115.00'],
                ],
            ],
            'motivos' => [
                ['razon' => 'Intereses por mora', 'valor' => '15.00'],
            ],
        ];
    }

    public function test_from_array_builds_aggregate(): void
    {
        $nd = NotaDebito::fromArray($this->validData());

        $this->assertSame('1790011001001', $nd->infoTributaria->ruc);
        $this->assertSame('15/03/2026', $nd->fechaEmision);
        $this->assertSame('01', $nd->codDocModificado);
        $this->assertSame('115.00', $nd->valorTotal->format(2));
        $this->assertCount(1, $nd->impuestos);
        $this->assertCount(1, $nd->pagos);
        $this->assertCount(1, $nd->motivos);
        $this->assertInstanceOf(Motivo::class, $nd->motivos[0]);
        $this->assertSame('Intereses por mora', $nd->motivos[0]->razon);
        $this->assertSame('15.00', $nd->motivos[0]->valor->format(2));
    }

    public function test_rejects_without_motivos(): void
    {
        $data = $this->validData();
        $data['motivos'] = [];

        $this->expectException(ValidationException::class);
        NotaDebito::fromArray($data);
    }

    public function test_rejects_invalid_fecha_emision(): void
    {
        $data = $this->validData();
        $data['infoNotaDebito']['fechaEmision'] = '2026-03-15';

        $this->expectException(ValidationException::class);
        NotaDebito::fromArray($data);
    }

    public function test_rejects_iso_fecha_emision_doc_sustento(): void
    {
        $data = $this->validData();
        $data['infoNotaDebito']['fechaEmisionDocSustento'] = '2026-02-01';

        $this->expectException(ValidationException::class);
        NotaDebito::fromArray($data);
    }
}
